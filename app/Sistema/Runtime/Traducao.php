<?php

namespace App\Sistema\Runtime;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Models\Md\MdTabelaTraducao;
use App\Sistema\Enums\TipoCampo;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Traduções de uma tabela (md_tabela_traducoes): como um registro dela
 * aparece quando outra tabela aponta para ela (FK). "tabela" vale nos
 * datagrids, "lookup" nos formulários. Os campos são unidos com " - ":
 * [CODIGO, NOME] => "001 - Bebidas".
 *
 * Sem tradução escolhida: a padrão do tipo, a primeira do tipo, a padrão do
 * outro tipo, o campo de exibição antigo e, por fim, o id.
 */
class Traducao
{
    public const TABELA = MdTabelaTraducao::TABELA;
    public const LOOKUP = MdTabelaTraducao::LOOKUP;

    public const SEPARADOR = ' - ';

    /**
     * Tipos que não fazem sentido numa tradução.
     */
    public static function aceita(TipoCampo $tipo): bool
    {
        return ! in_array($tipo, [TipoCampo::Password, TipoCampo::File, TipoCampo::Image], true) && ! $tipo->isJson();
    }

    public static function escolher(MdTabela $tabela, string $tipo, ?int $traducaoId = null): ?MdTabelaTraducao
    {
        $tabela->loadMissing('traducoes');
        $todas = $tabela->traducoes;

        $escolhida = $traducaoId ? $todas->firstWhere('id', $traducaoId) : null;

        if ($escolhida) {
            return $escolhida;
        }

        $doTipo = $todas->where('tipo', $tipo);
        $outro = $todas->where('tipo', $tipo === static::LOOKUP ? static::TABELA : static::LOOKUP);

        return $doTipo->firstWhere('padrao', true) ?? $doTipo->first() ?? $outro->firstWhere('padrao', true) ?? $outro->first();
    }

    /**
     * Campos da tradução, em ordem.
     *
     * @return list<MdCampo|string> MdCampo, ou 'id'
     */
    public static function campos(MdTabela $tabela, string $tipo, ?int $traducaoId = null): array
    {
        $tabela->loadMissing('campos');
        $porUid = $tabela->campos->keyBy('uid');

        $campos = collect(static::escolher($tabela, $tipo, $traducaoId)?->campos ?? [])
            ->map(fn ($uid) => $porUid->get(is_array($uid) ? ($uid['campo'] ?? null) : $uid))
            ->filter()
            ->values()
            ->all();

        if ($campos !== []) {
            return $campos;
        }

        $exibicao = $tabela->campo_exibicao ? $tabela->campos->firstWhere('nome_fisico', $tabela->campo_exibicao) : null;

        return [$exibicao ?? 'id'];
    }

    /**
     * Nomes físicos das colunas da tradução.
     *
     * @return list<string>
     */
    public static function colunas(MdTabela $tabela, string $tipo, ?int $traducaoId = null): array
    {
        return array_map(fn ($c) => $c instanceof MdCampo ? $c->nome_fisico : $c, static::campos($tabela, $tipo, $traducaoId));
    }

    /**
     * Texto da tradução de um registro (campos vazios ficam de fora).
     */
    public static function texto(?Model $registro, MdTabela $tabela, string $tipo, ?int $traducaoId = null): ?string
    {
        if (! $registro) {
            return null;
        }

        $partes = collect(static::campos($tabela, $tipo, $traducaoId))
            ->map(fn ($campo) => static::formatar($registro, $campo))
            ->filter(fn ($valor) => $valor !== null && $valor !== '')
            ->all();

        return $partes === [] ? (string) $registro->getKey() : implode(static::SEPARADOR, $partes);
    }

    protected static function formatar(Model $registro, MdCampo|string $campo): ?string
    {
        if (is_string($campo)) {
            return (string) $registro->getAttribute($campo);
        }

        $valor = $registro->getAttribute($campo->nome_fisico);

        return match (true) {
            $valor === null => null,
            $campo->tipo === TipoCampo::Boolean => $valor ? 'Sim' : 'Não',
            $valor instanceof DateTimeInterface => $campo->tipo->isDate() ? $valor->format('d/m/Y') : $valor->format('d/m/Y H:i'),
            $campo->tipo === TipoCampo::Decimal => number_format((float) $valor, $campo->escala ?? 2, ',', '.'),
            is_array($valor) => implode(', ', $valor),
            default => (string) $valor,
        };
    }

    /**
     * Busca: algum campo da tradução contém o texto.
     */
    public static function buscar(Builder $query, MdTabela $tabela, string $tipo, string $termo, ?int $traducaoId = null): Builder
    {
        $grammar = $query->getModel()->getConnection()->getQueryGrammar();

        return $query->where(function (Builder $q) use ($tabela, $tipo, $termo, $grammar, $traducaoId) {
            foreach (static::colunas($tabela, $tipo, $traducaoId) as $coluna) {
                $q->orWhereRaw('cast(' . $grammar->wrap($q->getModel()->qualifyColumn($coluna)) . ' as text) ilike ?', ['%' . $termo . '%']);
            }
        });
    }

    /**
     * Ordena pela tradução (na ordem dos campos).
     */
    public static function ordenar(Builder $query, MdTabela $tabela, string $tipo, ?int $traducaoId = null): Builder
    {
        foreach (static::colunas($tabela, $tipo, $traducaoId) as $coluna) {
            $query->orderBy($coluna);
        }

        return $query;
    }

    /**
     * Opções do combo de tradução: id => nome.
     *
     * @return array<int, string>
     */
    public static function opcoes(?MdTabela $tabela, string $tipo): array
    {
        return $tabela ? $tabela->traducoes()->where('tipo', $tipo)->pluck('nome', 'id')->all() : [];
    }
}
