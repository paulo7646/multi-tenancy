<?php

namespace App\Sistema\Schema;

use App\Models\Md\MdTabela;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passa as tabelas físicas do dicionário para nomes em maiúscula (op_produto
 * -> OP_PRODUTO, nome -> NOME), no banco da conexão atual. Usado pelas
 * migrations "renomear_tabelas_maiusculas" (central e clientes); não faz
 * nada onde já está em maiúscula.
 */
class RenomearMaiusculas
{
    public static function executar(string $banco): void
    {
        foreach (MdTabela::with('campos')->where('banco', $banco)->whereNotNull('generated_at')->get() as $tabela) {
            static::tabela($tabela);
        }
    }

    protected static function tabela(MdTabela $tabela): void
    {
        $novo = $tabela->nome_fisico; // já em maiúscula no dicionário
        $antigo = mb_strtolower($novo);

        if ($antigo === $novo || ! Schema::hasTable($antigo) || Schema::hasTable($novo)) {
            return;
        }

        $q = fn (string $id) => '"' . str_replace('"', '""', $id) . '"';

        DB::statement("alter table {$q($antigo)} rename to {$q($novo)}");

        $colunas = Schema::getColumnListing($novo);

        foreach ([...$tabela->campos->pluck('nome_fisico')->all(), 'SITUACAO'] as $coluna) {
            if (in_array(mb_strtolower($coluna), $colunas, true) && ! in_array($coluna, $colunas, true)) {
                DB::statement("alter table {$q($novo)} rename column {$q(mb_strtolower($coluna))} to {$q($coluna)}");
            }
        }

        // constraints e índices com o nome antigo da tabela no começo
        $prefixo = "{$antigo}_";

        foreach (DB::select('select conname from pg_constraint where conrelid = ?::regclass', [$q($novo)]) as $c) {
            if (str_starts_with($c->conname, $prefixo)) {
                DB::statement("alter table {$q($novo)} rename constraint {$q($c->conname)} to {$q($novo . '_' . substr($c->conname, strlen($prefixo)))}");
            }
        }

        foreach (DB::select('select indexname from pg_indexes where tablename = ?', [$novo]) as $i) {
            if (str_starts_with($i->indexname, $prefixo)) {
                DB::statement("alter index {$q($i->indexname)} rename to {$q($novo . '_' . substr($i->indexname, strlen($prefixo)))}");
            }
        }

        // situação: os códigos agora são em maiúscula (ATIVO)
        if (in_array('SITUACAO', Schema::getColumnListing($novo), true)) {
            $check = SchemaSnapshot::constraintName($novo, SchemaSnapshot::UID_SITUACAO, 'check');
            $opcoes = $tabela->generated_schema['fields'][SchemaSnapshot::UID_SITUACAO]['options'] ?? [];

            DB::statement("alter table {$q($novo)} drop constraint if exists {$q($check)}");
            DB::statement("update {$q($novo)} set \"SITUACAO\" = upper(\"SITUACAO\") where \"SITUACAO\" is not null");
            DB::statement("alter table {$q($novo)} alter column \"SITUACAO\" set default " . DB::getPdo()->quote((string) ($tabela->generated_schema['fields'][SchemaSnapshot::UID_SITUACAO]['default'] ?? '')));

            if ($opcoes !== []) {
                $valores = implode(', ', array_map(fn ($o) => DB::getPdo()->quote((string) $o), $opcoes));
                DB::statement("alter table {$q($novo)} add constraint {$q($check)} check (\"SITUACAO\" in ({$valores}))");
            }
        }
    }
}
