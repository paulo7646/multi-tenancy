<?php

namespace App\Sistema\Schema;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cópia da tabela md_status (banco central) em cada cliente, com os mesmos ids,
 * para as tabelas dos clientes que controlam situação terem a chave estrangeira
 * MD_STATUS_ID de verdade (o banco não liga tabelas de bancos diferentes).
 */
class StatusNoTenant
{
    private const COLUNAS = ['id', 'nome', 'nome_fisico', 'icone', 'cor', 'descricao', 'created_at', 'updated_at'];

    /**
     * Copia o central para o banco da conexão atual (o do cliente).
     */
    public static function sincronizar(): void
    {
        if (! Schema::hasTable('md_status')) {
            return;
        }

        $status = DB::connection('central')->table('md_status')->get(static::COLUNAS)
            ->map(fn ($linha) => (array) $linha)
            ->all();

        foreach ($status as $linha) {
            DB::table('md_status')->updateOrInsert(['id' => $linha['id']], $linha);
        }

        // situações removidas (se alguma tabela ainda usar, a FK impede)
        DB::table('md_status')->whereNotIn('id', array_column($status, 'id'))->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select setval(pg_get_serial_sequence('md_status', 'id'), coalesce(max(id), 1)) from md_status");
        }
    }

    /**
     * Copia para todos os clientes (ao mudar as situações no dicionário).
     */
    public static function sincronizarTodos(): void
    {
        Tenant::all()->each(fn (Tenant $tenant) => $tenant->run(fn () => static::sincronizar()));
    }
}
