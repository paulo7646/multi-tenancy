<?php

use App\Sistema\Schema\StatusNoTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas da Estrutura feitas à mão passam a controlar situação como as criadas pela aba Tabelas:
 * coluna MD_STATUS_ID (opcional, com a situação Ativo por padrão) ligada à cópia de md_status do cliente.
 * O código escrito à mão não usa a coluna.
 */
return new class extends Migration
{
    private const COLUNA = 'MD_STATUS_ID';

    private const TABELAS = ['licencas', 'filiais', 'users', 'plt_configuracoes', 'audits'];

    public function up(): void
    {
        StatusNoTenant::sincronizar();

        $ativo = DB::table('md_status')->where('nome_fisico', 'ATIVO')->value('id');

        foreach (self::TABELAS as $tabela) {
            if (! Schema::hasTable($tabela) || Schema::hasColumn($tabela, self::COLUNA)) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $table) use ($ativo) {
                $table->unsignedBigInteger(self::COLUNA)->nullable()->default($ativo)->index();
                $table->foreign(self::COLUNA)->references('id')->on('md_status')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, self::COLUNA)) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->dropForeign([self::COLUNA]);
                    $table->dropColumn(self::COLUNA);
                });
            }
        }
    }
};
