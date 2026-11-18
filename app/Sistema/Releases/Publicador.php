<?php

namespace App\Sistema\Releases;

use App\Models\Md\MdRelease;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Geradores\Exportador;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Gerador de pacotes: pega o que mudou no módulo (dicionário e migrations) e publica uma release.
 * O cliente só aplica as releases dos módulos que tem (ver Aplicador).
 */
class Publicador
{
    /**
     * @throws RuntimeException se o módulo não é de cliente ou não tem novidade desde a última release
     */
    public static function publicar(Modulo $modulo, ?string $notas = null): MdRelease
    {
        if ($modulo->banco !== Banco::Tenant) {
            throw new RuntimeException('Só módulos de clientes têm release: o da base pai já roda no próprio banco.');
        }

        $pacote = static::pacote($modulo);
        $hash = static::hashDoPacote($pacote);

        $ultima = MdRelease::where('modulo_id', $modulo->id)->orderByDesc('versao')->first();

        if ($ultima?->hash === $hash) {
            throw new RuntimeException("Sem novidade no módulo {$modulo->nome} desde a versão {$ultima->versao}.");
        }

        return MdRelease::create([
            'modulo_id' => $modulo->id,
            'versao' => ($ultima?->versao ?? 0) + 1,
            'notas' => $notas,
            'pacote' => $pacote,
            'hash' => $hash,
            'publicado_por' => auth()->user()?->email,
            'publicado_em' => now(),
        ]);
    }

    /**
     * Identidade do pacote: o que muda de verdade. O carimbo generated_at muda a cada geração mesmo sem
     * mudança estrutural, então fica de fora (senão toda geração viraria "novidade").
     *
     * @param  array<string, mixed>  $pacote
     */
    protected static function hashDoPacote(array $pacote): string
    {
        $pacote['tabelas'] = array_map(fn (array $tabela) => collect($tabela)->except('generated_at')->all(), $pacote['tabelas']);

        return hash('sha256', json_encode($pacote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * O conteúdo do pacote: o módulo, o cadastro das tabelas dele e as migrations (arquivo e hash).
     *
     * @return array{modulo: array<string, mixed>, tabelas: list<array<string, mixed>>, migrations: list<array{arquivo: string, hash: string}>}
     */
    public static function pacote(Modulo $modulo): array
    {
        $tabelas = MdTabela::query()->where('modulo_id', $modulo->id)->orderBy('nome_fisico')->get()
            ->map(fn (MdTabela $t) => Exportador::dadosDaTabela($t))
            ->all();

        $pasta = Banco::Tenant->migrationsPath($modulo);

        $migrations = collect(File::isDirectory($pasta) ? File::files($pasta) : [])
            ->filter(fn ($arquivo) => $arquivo->getExtension() === 'php')
            ->sortBy(fn ($arquivo) => $arquivo->getFilename())
            ->map(fn ($arquivo) => ['arquivo' => $arquivo->getFilename(), 'hash' => hash_file('sha256', $arquivo->getPathname())])
            ->values()
            ->all();

        return [
            'modulo' => $modulo->only(['nome', 'prefixo', 'pasta', 'descricao', 'padrao']),
            'tabelas' => $tabelas,
            'migrations' => $migrations,
        ];
    }
}
