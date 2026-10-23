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
        MigrationGuard::emptyTable('OP_PEDIDO', 'NOME');

        MigrationGuard::emptyTable('OP_PEDIDO', 'NUMERO');

        MigrationGuard::emptyTable('OP_PEDIDO', 'PRODUTO');

        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->string('NOME', 255)->nullable(false);
            $table->string('NUMERO', 255)->nullable(false);
            $table->unsignedBigInteger('PRODUTO')->nullable(false);
        });

        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->unique('NUMERO', 'OP_PEDIDO_94de1c1e_unique');
            $table->foreign('PRODUTO', 'OP_PEDIDO_9ee58b1b_foreign')->references('id')->on('OP_PRODUTO')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->dropUnique('OP_PEDIDO_94de1c1e_unique');
            $table->dropForeign('OP_PEDIDO_9ee58b1b_foreign');
        });

        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->dropColumn('NOME');
            $table->dropColumn('NUMERO');
            $table->dropColumn('PRODUTO');
        });
    }
};
