<?php

namespace App\Sistema\Schema;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Verificações chamadas pelas migrations geradas, antes de alterar a tabela.
 * Falham com uma mensagem clara (em vez do erro cru do banco) dizendo o
 * banco, a tabela, a coluna e quantos registros impedem a alteração.
 *
 * No PostgreSQL cada migration roda numa transação: se uma verificação
 * falhar, nada daquela migration fica aplicado.
 */
class MigrationGuard
{
    /**
     * Coluna nova obrigatória sem valor padrão: só dá se a tabela estiver vazia.
     */
    public static function emptyTable(string $table, string $column): void
    {
        $rows = DB::table($table)->count();

        if ($rows > 0) {
            static::fail($table, $column, "campo novo obrigatório sem valor padrão, mas a tabela tem {$rows} registro(s). Informe um valor padrão ou marque \"Aceita vazio\".");
        }
    }

    /**
     * Coluna que passou a ser obrigatória (sem valor padrão para preencher os vazios).
     */
    public static function noNulls(string $table, string $column): void
    {
        $nulls = DB::table($table)->whereNull($column)->count();

        if ($nulls > 0) {
            static::fail($table, $column, "ficou obrigatório, mas {$nulls} registro(s) estão vazios. Informe um valor padrão (ele preenche os vazios) ou mantenha \"Aceita vazio\".");
        }
    }

    /**
     * Troca de tipo: todos os valores precisam converter para o tipo novo.
     */
    public static function castable(string $table, string $column, string $cast): void
    {
        $where = sprintf('%1$s is not null and not pg_input_is_valid(%1$s::text, ?)', static::quote($column));

        try {
            $invalid = DB::table($table)->whereRaw($where, [$cast])->count();
        } catch (Throwable) {
            return; // pg_input_is_valid só existe no PostgreSQL 16+; a conversão em si acusa o erro
        }

        if ($invalid > 0) {
            $examples = DB::table($table)->whereRaw($where, [$cast])->limit(3)->pluck($column)
                ->map(fn ($value) => '"' . $value . '"')->join(', ');

            static::fail($table, $column, "{$invalid} registro(s) não convertem para o tipo {$cast}. Exemplos: {$examples}.");
        }
    }

    public static function noDuplicates(string $table, string $column): void
    {
        $duplicates = DB::table($table)->select($column)->whereNotNull($column)
            ->groupBy($column)->havingRaw('count(*) > 1')->get()->count();

        if ($duplicates > 0) {
            static::fail($table, $column, "não dá para marcar como único: há {$duplicates} valor(es) repetido(s).");
        }
    }

    public static function references(string $table, string $column, string $foreignTable): void
    {
        $orphans = DB::table($table)->whereNotNull($column)
            ->whereNotIn($column, DB::table($foreignTable)->select('id'))
            ->count();

        if ($orphans > 0) {
            static::fail($table, $column, "{$orphans} registro(s) apontam para ids que não existem em {$foreignTable}.");
        }
    }

    public static function onlyOptions(string $table, string $column, array $options): void
    {
        $outside = DB::table($table)->whereNotNull($column)->whereNotIn($column, $options)->count();

        if ($outside > 0) {
            static::fail($table, $column, "{$outside} registro(s) usam opções que não estão na lista (" . implode(', ', $options) . ').');
        }
    }

    protected static function fail(string $table, string $column, string $problem): never
    {
        $database = DB::connection()->getDatabaseName();

        throw new RuntimeException("[banco {$database}] {$table}.{$column}: {$problem}");
    }

    protected static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
