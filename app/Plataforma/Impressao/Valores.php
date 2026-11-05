<?php

namespace App\Plataforma\Impressao;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Valores de um registro como texto para impressão e planilhas: data formatada,
 * Sim/Não, nome do registro ligado (chave estrangeira) e situação com o nome.
 */
class Valores
{
    /**
     * Colunas imprimíveis da tabela: [rótulo, campo (MdCampo) ou 'situacao'|'id'].
     *
     * @return list<array{0: string, 1: MdCampo|string}>
     */
    public static function colunas(MdTabela $tabela): array
    {
        $colunas = [['ID', 'id']];

        foreach ($tabela->campos()->orderBy('ordem')->get() as $campo) {
            if ($campo->tipo !== TipoCampo::Password) {
                $colunas[] = [$campo->nome, $campo];
            }
        }

        if ($tabela->controla_situacao) {
            $colunas[] = ['Situação', 'situacao'];
        }

        return $colunas;
    }

    public static function de(Model $registro, MdCampo|string $coluna, MdTabela $tabela): string
    {
        if ($coluna === 'id') {
            return (string) $registro->getKey();
        }

        if ($coluna === 'situacao') {
            $id = $registro->getAttribute(MdTabela::COLUNA_SITUACAO);

            return (string) ($tabela->situacoes->first(fn ($s) => $s->md_status_id === (int) $id)?->status?->nome ?? '');
        }

        $valor = $registro->getAttribute($coluna->nome_fisico);

        if ($valor === null || $valor === '') {
            return '';
        }

        return match (true) {
            $coluna->isFk() => static::registroLigado($registro, $coluna) ?? (string) $valor,
            $coluna->tipo === TipoCampo::Boolean => $valor ? 'Sim' : 'Não',
            $coluna->tipo->isDateTime() => static::data($valor, 'd/m/Y H:i'),
            $coluna->tipo->isDate() => static::data($valor, 'd/m/Y'),
            $coluna->tipo->isJson() || $coluna->tipo === TipoCampo::MultiSelect => is_array($valor) ? implode(', ', array_map('strval', $valor)) : (string) $valor,
            in_array($coluna->tipo, [TipoCampo::Decimal, TipoCampo::Float, TipoCampo::Double], true) => number_format((float) $valor, (int) ($coluna->escala ?? 2), ',', '.'),
            default => is_scalar($valor) || $valor instanceof \Stringable ? (string) $valor : json_encode($valor),
        };
    }

    protected static function registroLigado(Model $registro, MdCampo $campo): ?string
    {
        try {
            $ligado = $registro->{$campo->nomeRelacao()};
            $exibicao = $campo->fkTabela?->campo_exibicao;

            return $ligado && $exibicao ? (string) $ligado->getAttribute($exibicao) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function data(mixed $valor, string $formato): string
    {
        try {
            return Carbon::parse($valor)->format($formato);
        } catch (Throwable) {
            return (string) $valor;
        }
    }
}
