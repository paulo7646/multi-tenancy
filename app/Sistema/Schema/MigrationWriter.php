<?php

namespace App\Sistema\Schema;

use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;

/**
 * Gera o código das migrations das tabelas do dicionário (arquivos que vão
 * para o git; o cliente aplica com migrate / tenants:migrate).
 *
 * O snapshot ['fields' => [...]] é a foto da última migration gerada.
 * Comparando a foto antiga com a atual sai a migration de alteração. Campos
 * são indexados pelo uid: trocar o nome físico vira renameColumn.
 *
 * Proteções geradas junto:
 *  - verificações (MigrationGuard) antes de alterar: dados que não convertem,
 *    vazios, repetidos, ids inexistentes, opções fora da lista;
 *  - troca de tipo com USING (o Laravel não faz isso no PostgreSQL);
 *  - campo que ficou obrigatório com valor padrão: preenche os vazios antes.
 */
class MigrationWriter
{
    public static function create(MdTabela $tabela, array $new): string
    {
        $table = $tabela->nome_fisico;

        return static::file(
            [static::createBlock($table, $new['fields']), ...static::checks($table, [], $new['fields'])],
            ["Schema::dropIfExists('{$table}');"],
        );
    }

    /**
     * Retorna null se não houver diferença.
     */
    public static function alter(MdTabela $tabela, array $old, array $new): ?string
    {
        $up = static::alterStatements($tabela->nome_fisico, $old['fields'], $new['fields']);
        $down = static::alterStatements($tabela->nome_fisico, $new['fields'], $old['fields']);

        if ($up === []) {
            return null;
        }

        return static::file($up, $down ?: ['//']);
    }

    /**
     * Apaga a tabela. O rollback recria a estrutura (sem os dados).
     */
    public static function drop(MdTabela $tabela, array $old): string
    {
        $table = $tabela->nome_fisico;

        return static::file(
            ["Schema::dropIfExists('{$table}');"],
            [static::createBlock($table, $old['fields']), ...static::checks($table, [], $old['fields'])],
        );
    }

    // ------------------------------------------------------------------

    protected static function createBlock(string $table, array $fields): string
    {
        $lines = ['$table->id();'];

        foreach ($fields as $field) {
            $lines[] = static::column($field) . ';';
        }

        $lines[] = '$table->timestamps();';

        foreach ($fields as $id => $field) {
            array_push($lines, ...static::addConstraints($table, (string) $id, null, $field));
        }

        return static::schemaBlock('create', $table, $lines);
    }

    /**
     * @return list<string>
     */
    protected static function alterStatements(string $table, array $old, array $new): array
    {
        $guards = $constraintsOff = $renames = $drops = $sql = $changes = $adds = $constraintsOn = [];

        foreach ($old as $id => $before) {
            $id = (string) $id;
            $after = $new[$id] ?? null;

            if ($before['foreign'] && (! $after || $after['foreign'] !== $before['foreign'])) {
                $constraintsOff[] = "\$table->dropForeign('" . SchemaSnapshot::constraintName($table, $id, 'foreign') . "');";
            }

            if ($before['unique'] && (! $after || ! $after['unique'])) {
                $constraintsOff[] = "\$table->dropUnique('" . SchemaSnapshot::constraintName($table, $id, 'unique') . "');";
            }

            if ($before['index'] && (! $after || ! $after['index'])) {
                $constraintsOff[] = "\$table->dropIndex('" . SchemaSnapshot::constraintName($table, $id, 'index') . "');";
            }

            if ($before['options'] !== null && (! $after || $after['options'] !== $before['options'])) {
                $sql[] = static::statement('alter table ' . static::quote($table) . ' drop constraint if exists ' . static::quote(SchemaSnapshot::constraintName($table, $id, 'check')));
            }

            if (! $after) {
                $drops[] = "\$table->dropColumn('{$before['name']}');";

                continue;
            }

            // verificações usam o nome antigo: rodam antes do rename
            $current = $before['name'];

            if (! $after['nullable'] && $before['nullable'] && $after['default'] === null) {
                $guards[] = "MigrationGuard::noNulls('{$table}', '{$current}');";
            }

            if ($after['name'] !== $before['name']) {
                $renames[] = "\$table->renameColumn('{$before['name']}', '{$after['name']}');";
            }

            if (static::typeChanged($before, $after)) {
                $cast = static::cast($after);
                $column = static::quote($after['name']);

                $guards[] = "MigrationGuard::castable('{$table}', '{$current}', " . var_export($cast, true) . ');';
                $sql[] = static::statement('alter table ' . static::quote($table) . " alter column {$column} drop default");
                $sql[] = static::statement('alter table ' . static::quote($table) . " alter column {$column} type {$cast} using {$column}::{$cast}");
            }

            if ($before['nullable'] && ! $after['nullable'] && $after['default'] !== null) {
                $sql[] = "DB::table('{$table}')->whereNull('{$after['name']}')->update(['{$after['name']}' => "
                    . static::export(SchemaSnapshot::castDefault(TipoCampo::from($after['type']), $after['default'])) . ']);';
            }

            if (static::columnChanged($before, $after)) {
                $changes[] = static::column($after) . '->change();';
            }

            if ($after['unique'] && ! $before['unique']) {
                $guards[] = "MigrationGuard::noDuplicates('{$table}', '{$current}');";
            }

            if ($after['foreign'] && $after['foreign'] !== $before['foreign']) {
                $guards[] = "MigrationGuard::references('{$table}', '{$current}', '{$after['foreign']['table']}');";
            }

            if ($after['options'] !== null && $after['options'] !== $before['options']) {
                $guards[] = "MigrationGuard::onlyOptions('{$table}', '{$current}', " . static::export($after['options']) . ');';
            }
        }

        foreach ($new as $id => $after) {
            $id = (string) $id;
            $before = $old[$id] ?? null;

            if (! $before) {
                if (! $after['nullable'] && $after['default'] === null) {
                    $guards[] = "MigrationGuard::emptyTable('{$table}', '{$after['name']}');";
                }

                $adds[] = static::column($after) . ';';
            }

            array_push($constraintsOn, ...static::addConstraints($table, $id, $before, $after));
        }

        return array_values(array_filter([
            ...$guards,
            static::schemaBlock('table', $table, $constraintsOff),
            static::schemaBlock('table', $table, $renames),
            static::schemaBlock('table', $table, $drops),
            ...$sql,
            static::schemaBlock('table', $table, $changes),
            static::schemaBlock('table', $table, $adds),
            static::schemaBlock('table', $table, $constraintsOn),
            ...static::checks($table, $old, $new),
        ]));
    }

    /**
     * Regra de opções (enum) no banco, para campos novos ou com opções trocadas.
     *
     * @return list<string>
     */
    protected static function checks(string $table, array $old, array $new): array
    {
        $statements = [];

        foreach ($new as $id => $after) {
            $before = $old[$id] ?? null;

            if ($after['options'] === null || ($before && $before['options'] === $after['options'])) {
                continue;
            }

            $values = implode(', ', array_map(fn ($option) => "'" . str_replace("'", "''", (string) $option) . "'", $after['options']));

            $statements[] = static::statement('alter table ' . static::quote($table)
                . ' add constraint ' . static::quote(SchemaSnapshot::constraintName($table, (string) $id, 'check'))
                . ' check (' . static::quote($after['name']) . " in ({$values}))");
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    protected static function addConstraints(string $table, string $id, ?array $before, array $after): array
    {
        $lines = [];

        if ($after['unique'] && ! ($before['unique'] ?? false)) {
            $lines[] = "\$table->unique('{$after['name']}', '" . SchemaSnapshot::constraintName($table, $id, 'unique') . "');";
        }

        if ($after['index'] && ! ($before['index'] ?? false)) {
            $lines[] = "\$table->index('{$after['name']}', '" . SchemaSnapshot::constraintName($table, $id, 'index') . "');";
        }

        if ($after['foreign'] && ($before['foreign'] ?? null) !== $after['foreign']) {
            $onDelete = match ($after['foreign']['on_delete']) {
                'cascade' => 'cascadeOnDelete',
                'null' => 'nullOnDelete',
                default => 'restrictOnDelete',
            };

            $lines[] = "\$table->foreign('{$after['name']}', '" . SchemaSnapshot::constraintName($table, $id, 'foreign') . "')"
                . "->references('id')->on('{$after['foreign']['table']}')->{$onDelete}();";
        }

        return $lines;
    }

    public static function typeChanged(array $before, array $after): bool
    {
        return static::cast($before) !== static::cast($after);
    }

    protected static function cast(array $field): string
    {
        return TipoCampo::from($field['type'])->postgresCast($field['length'], $field['precision'], $field['scale']);
    }

    protected static function columnChanged(array $before, array $after): bool
    {
        foreach (['type', 'length', 'precision', 'scale', 'nullable', 'default'] as $key) {
            if ($before[$key] !== $after[$key]) {
                return true;
            }
        }

        return false;
    }

    protected static function column(array $field): string
    {
        $type = TipoCampo::from($field['type']);
        $name = var_export($field['name'], true);

        $code = match (true) {
            $type->isForeign() => "\$table->unsignedBigInteger({$name})",
            $type === TipoCampo::Enum => "\$table->string({$name}, 255)", // opções viram CHECK
            $type->hasLength() => "\$table->{$type->value}({$name}, {$field['length']})",
            $type === TipoCampo::Decimal => "\$table->decimal({$name}, {$field['precision']}, {$field['scale']})",
            $type === TipoCampo::Float => "\$table->float({$name}, {$field['precision']})",
            default => "\$table->{$type->value}({$name})",
        };

        $code .= $field['nullable'] ? '->nullable()' : '->nullable(false)';

        if ($field['default'] !== null) {
            $code .= '->default(' . static::export(SchemaSnapshot::castDefault($type, $field['default'])) . ')';
        }

        return $code;
    }

    protected static function statement(string $sql): string
    {
        return 'DB::statement(' . var_export($sql, true) . ');';
    }

    protected static function schemaBlock(string $method, string $table, array $lines): ?string
    {
        if ($lines === []) {
            return null;
        }

        return "Schema::{$method}('{$table}', function (Blueprint \$table) {\n            "
            . implode("\n            ", $lines)
            . "\n        });";
    }

    protected static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    protected static function export(mixed $value): string
    {
        if (is_array($value)) {
            return '[' . implode(', ', array_map(fn ($item) => static::export($item), $value)) . ']';
        }

        return var_export($value, true);
    }

    /**
     * @param  list<string>  $up
     * @param  list<string>  $down
     */
    protected static function file(array $up, array $down): string
    {
        $up = implode("\n\n        ", $up);
        $down = implode("\n\n        ", $down);

        return <<<PHP
<?php

// Gerado pelo dicionário de dados (md_tabelas).

use App\Sistema\Schema\MigrationGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        {$up}
    }

    public function down(): void
    {
        {$down}
    }
};

PHP;
    }
}
