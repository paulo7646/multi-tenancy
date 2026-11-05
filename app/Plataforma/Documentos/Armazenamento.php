<?php

namespace App\Plataforma\Documentos;

use App\Plataforma\Models\Arquivo;
use App\Plataforma\Models\Versao;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda os arquivos dos documentos: valida (extensão, tamanho, cota), grava no
 * disco configurado e registra a versão com o hash do conteúdo.
 *
 * Em um cliente, o disco local já é separado por empresa (Stancl Tenancy).
 */
class Armazenamento
{
    public static function disco(): string
    {
        return (string) config('plataforma.documentos.disco', 'local');
    }

    /**
     * Cria um documento com a primeira versão.
     *
     * @param  array{nome?: string, pasta_id?: int|null, descricao?: string|null, tags?: array|null}  $dados
     * @param  array{conteudo: string, nome: string, mime?: string|null}  $origem  veja deUpload()
     *
     * @throws DocumentoException
     */
    public static function criar(array $dados, array $origem, ?string $comentario = null): Arquivo
    {
        static::validar($origem);

        return DB::transaction(function () use ($dados, $origem, $comentario) {
            $arquivo = Arquivo::create([
                'nome' => $dados['nome'] ?? $origem['nome'],
                'pasta_id' => $dados['pasta_id'] ?? null,
                'descricao' => $dados['descricao'] ?? null,
                'tags' => $dados['tags'] ?? null,
                ...static::autor(),
            ]);

            static::gravarVersao($arquivo, $origem, $comentario);

            return $arquivo->refresh();
        });
    }

    /**
     * Adiciona uma versão nova; ela passa a ser a atual.
     *
     * @param  array{conteudo: string, nome: string, mime?: string|null}  $origem
     *
     * @throws DocumentoException
     */
    public static function novaVersao(Arquivo $arquivo, array $origem, ?string $comentario = null): Versao
    {
        static::validar($origem);

        return DB::transaction(fn () => static::gravarVersao($arquivo, $origem, $comentario));
    }

    /**
     * @return array{conteudo: string, nome: string, mime: string|null}
     */
    public static function deUpload(UploadedFile $arquivo): array
    {
        return [
            'conteudo' => (string) file_get_contents($arquivo->getRealPath()),
            'nome' => $arquivo->getClientOriginalName(),
            'mime' => null, // detectado pelo conteúdo, não pelo que o navegador diz
        ];
    }

    /**
     * Apaga o documento de vez (todas as versões e os arquivos no disco).
     */
    public static function apagarDefinitivamente(Arquivo $arquivo): void
    {
        DB::transaction(function () use ($arquivo) {
            foreach ($arquivo->versoes as $versao) {
                Storage::disk($versao->disco)->delete($versao->caminho);
            }

            $arquivo->forceDelete();
        });
    }

    /**
     * Bytes ocupados por todas as versões guardadas.
     */
    public static function usado(): int
    {
        return (int) Versao::query()->sum('tamanho');
    }

    protected static function gravarVersao(Arquivo $arquivo, array $origem, ?string $comentario): Versao
    {
        $conteudo = $origem['conteudo'];
        $extensao = strtolower(pathinfo($origem['nome'], PATHINFO_EXTENSION));
        $caminho = 'documentos/'.now()->format('Y/m').'/'.Str::uuid().($extensao !== '' ? ".{$extensao}" : '');
        $disco = static::disco();

        Storage::disk($disco)->put($caminho, $conteudo);

        $versao = $arquivo->versoes()->create([
            'numero' => (int) $arquivo->versoes()->max('numero') + 1,
            'disco' => $disco,
            'caminho' => $caminho,
            'nome_original' => $origem['nome'],
            'mime' => $origem['mime'] ?? static::detectarMime($conteudo, $extensao),
            'tamanho' => strlen($conteudo),
            'hash' => hash('sha256', $conteudo),
            'comentario' => $comentario,
            ...static::autor(),
        ]);

        $arquivo->forceFill(['versao_atual_id' => $versao->getKey()])->save();

        return $versao;
    }

    /**
     * @throws DocumentoException
     */
    protected static function validar(array $origem): void
    {
        $extensao = strtolower(pathinfo($origem['nome'], PATHINFO_EXTENSION));

        if (in_array($extensao, config('plataforma.documentos.extensoes_bloqueadas', []), true)) {
            throw new DocumentoException("Arquivos .{$extensao} não podem ser enviados.");
        }

        $tamanho = strlen($origem['conteudo']);
        $maximo = (int) config('plataforma.documentos.max_mb', 50) * 1024 * 1024;

        if ($tamanho === 0) {
            throw new DocumentoException('O arquivo está vazio.');
        }

        if ($tamanho > $maximo) {
            throw new DocumentoException('O arquivo passa do limite de '.config('plataforma.documentos.max_mb').' MB.');
        }

        $cota = config('plataforma.documentos.cota_mb');

        if ($cota !== null && static::usado() + $tamanho > (int) $cota * 1024 * 1024) {
            throw new DocumentoException("A cota de armazenamento ({$cota} MB) foi atingida.");
        }
    }

    protected static function detectarMime(string $conteudo, string $extensao): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo) ?: 'application/octet-stream';

        // finfo devolve text/plain para csv, json etc.: a extensão desempata
        return match (true) {
            $mime === 'text/plain' && $extensao === 'csv' => 'text/csv',
            $mime === 'text/plain' && $extensao === 'json' => 'application/json',
            default => $mime,
        };
    }

    /**
     * @return array{user_id: int|null, criado_por: string|null}
     */
    protected static function autor(): array
    {
        $usuario = auth()->user();

        return ['user_id' => $usuario?->getAuthIdentifier(), 'criado_por' => $usuario?->name];
    }
}
