<?php

namespace App\Sistema\Runtime;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;
use Illuminate\Support\Collection;

/**
 * Diagrama ER (Mermaid "erDiagram") das tabelas do dicionário: as colunas
 * (PK, FK, único) e as setas das chaves estrangeiras (pai 1 -> N filha).
 */
class DiagramaSchema
{
    /**
     * Todas as tabelas (de um banco, ou de todos).
     */
    public static function geral(?string $banco = null): string
    {
        $tabelas = static::consulta()->when($banco, fn ($q) => $q->where('banco', $banco))->get();

        return static::mermaid($tabelas);
    }

    /**
     * As tabelas escolhidas e as filhas delas (as que têm FK apontando para elas).
     *
     * @param  list<int>  $ids
     */
    public static function daTabela(array $ids): string
    {
        $filhas = MdCampo::query()
            ->whereIn('fk_tabela_id', $ids)
            ->where('tipo', TipoCampo::ForeignId->value)
            ->pluck('md_tabela_id');

        $tabelas = static::consulta()->whereIn('id', [...$ids, ...$filhas])->get();

        return static::mermaid($tabelas);
    }

    /**
     * Quantas tabelas e ligações entram no diagrama (para o título do modal).
     *
     * @return array{tabelas: int, ligacoes: int}
     */
    public static function contagem(Collection $tabelas): array
    {
        $ids = $tabelas->pluck('id')->all();

        return [
            'tabelas' => $tabelas->count(),
            'ligacoes' => $tabelas->sum(fn (MdTabela $t) => $t->campos->filter(fn (MdCampo $c) => $c->isFk() && in_array($c->fk_tabela_id, $ids))->count()),
        ];
    }

    protected static function consulta()
    {
        return MdTabela::query()
            ->with(['campos' => fn ($q) => $q->orderBy('ordem')])
            ->whereNotNull('generated_at')
            ->orderBy('nome_fisico');
    }

    /**
     * @param  Collection<int, MdTabela>  $tabelas
     */
    public static function mermaid(Collection $tabelas): string
    {
        // sem tabelas: a tela mostra o aviso (um "erDiagram" vazio quebraria o Mermaid)
        if ($tabelas->isEmpty()) {
            return '';
        }

        $ids = $tabelas->pluck('id')->all();
        $porId = $tabelas->keyBy('id');
        $linhas = ['erDiagram'];

        foreach ($tabelas as $tabela) {
            $linhas[] = '    ' . static::nome($tabela) . ' {';
            $linhas[] = '        bigint id PK';

            foreach ($tabela->campos as $campo) {
                $chaves = array_filter([
                    $campo->isFk() ? 'FK' : null,
                    $campo->unico ? 'UK' : null,
                ]);

                $linhas[] = '        ' . static::tipo($campo) . ' ' . static::identificador($campo->nome_fisico)
                    . ($chaves ? ' ' . implode(', ', $chaves) : '')
                    . ' "' . static::texto($campo->nome) . ($campo->obrigatorio ? ' *' : '') . '"';
            }

            $linhas[] = '    }';
        }

        // pai ||--o{ filha : "CAMPO" (único: 1 para 0..1; obrigatória: o pai é obrigatório)
        foreach ($tabelas as $tabela) {
            foreach ($tabela->campos as $campo) {
                if (! $campo->isFk() || ! in_array($campo->fk_tabela_id, $ids)) {
                    continue;
                }

                $pai = $porId[$campo->fk_tabela_id];
                $ladoPai = $campo->obrigatorio ? '||' : '|o';
                $ladoFilha = $campo->unico ? 'o|' : 'o{';

                $linhas[] = '    ' . static::nome($pai) . " {$ladoPai}--{$ladoFilha} " . static::nome($tabela) . ' : "' . static::texto($campo->nome_fisico) . '"';
            }
        }

        return implode("\n", $linhas);
    }

    /** Nome da entidade: o nome físico (letras, números e _) */
    protected static function nome(MdTabela $tabela): string
    {
        return static::identificador($tabela->nome_fisico);
    }

    protected static function identificador(string $valor): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $valor) ?: 'X';
    }

    /** Tipo curto da coluna (uma palavra) */
    protected static function tipo(MdCampo $campo): string
    {
        $tipo = $campo->tipo->storageType();

        return match (true) {
            $tipo === TipoCampo::String => 'varchar' . ($campo->tamanho ? "({$campo->tamanho})" : ''),
            // o Mermaid não aceita vírgula no tipo: decimal(10,2) quebraria o diagrama
            $tipo === TipoCampo::Decimal => 'decimal',
            $campo->isFk() => 'bigint',
            default => static::identificador($tipo->value),
        };
    }

    /** Texto entre aspas no Mermaid (sem aspas nem quebras) */
    protected static function texto(?string $valor): string
    {
        return trim(str_replace(['"', "\n", "\r"], ["'", ' ', ' '], (string) $valor));
    }
}
