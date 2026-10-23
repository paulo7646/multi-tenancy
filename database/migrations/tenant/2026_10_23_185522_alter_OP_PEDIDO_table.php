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
        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->dropForeign('OP_PEDIDO_9ee58b1b_foreign');
        });

        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->dropColumn('PRODUTO');
        });
    }

    public function down(): void
    {
        MigrationGuard::emptyTable('OP_PEDIDO', 'PRODUTO');

        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->unsignedBigInteger('PRODUTO')->nullable(false);
        });

        Schema::table('OP_PEDIDO', function (Blueprint $table) {
            $table->foreign('PRODUTO', 'OP_PEDIDO_9ee58b1b_foreign')->references('id')->on('OP_PRODUTO')->restrictOnDelete();
        });
    }
};
