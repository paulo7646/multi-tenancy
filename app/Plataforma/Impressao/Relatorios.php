<?php

namespace App\Plataforma\Impressao;

use App\Models\Md\MdTabela;
use App\Plataforma\Models\Configuracao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Gera os PDFs: lista de uma tabela e ficha de um registro, com o cabeçalho da
 * empresa, no layout automático.
 */
class Relatorios
{
    /**
     * @param  iterable<Model>  $registros
     */
    public static function lista(MdTabela $tabela, iterable $registros, ?string $filtros = null): string
    {
        $tabela->loadMissing(['situacoes.status']);
        $colunas = Valores::colunas($tabela);

        $linhas = [];
        foreach ($registros as $registro) {
            $linhas[] = array_map(fn (array $c) => Valores::de($registro, $c[1], $tabela), $colunas);
        }

        return static::pdf('plataforma.impressao.lista', [
            'titulo' => $tabela->nome,
            'cabecalhos' => array_column($colunas, 0),
            'linhas' => $linhas,
            'filtros' => $filtros,
        ], count($colunas) > 6 ? 'landscape' : 'portrait');
    }

    public static function registro(MdTabela $tabela, Model $registro): string
    {
        $tabela->loadMissing(['situacoes.status']);
        $colunas = Valores::colunas($tabela);

        $dados = [];
        foreach ($colunas as [$rotulo, $coluna]) {
            $dados[] = [$rotulo, Valores::de($registro, $coluna, $tabela), is_object($coluna) ? $coluna->nome_fisico : strtoupper($coluna)];
        }

        return static::pdf('plataforma.impressao.registro', [
            'titulo' => "{$tabela->nome} #{$registro->getKey()}",
            'dados' => $dados,
        ], 'portrait');
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected static function pdf(string $visao, array $dados, string $orientacao): string
    {
        $empresa = Configuracao::empresa();

        return Pdf::loadView($visao, [
            ...$dados,
            'empresa' => $empresa,
            'logo' => static::logo($empresa['logo']),
            'cabecalho' => true,
            'rodape' => $empresa['rodape'],
            'emitidoPor' => auth()->user()?->name,
        ])->setPaper('a4', $orientacao)->output();
    }

    /**
     * O logo da empresa como imagem embutida (o PDF não busca nada na rede).
     */
    protected static function logo(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        $disco = Storage::disk('local');

        if (! $disco->exists($caminho)) {
            return null;
        }

        $conteudo = $disco->get($caminho);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo);

        return str_starts_with((string) $mime, 'image/') ? 'data:'.$mime.';base64,'.base64_encode($conteudo) : null;
    }
}
