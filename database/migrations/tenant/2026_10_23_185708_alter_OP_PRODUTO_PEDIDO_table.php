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
        MigrationGuard::emptyTable('OP_PRODUTO_PEDIDO', 'PRODUTO');

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->unsignedBigInteger('PRODUTO')->nullable(false);
        });

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->foreign('PRODUTO', 'OP_PRODUTO_PEDIDO_9140da64_foreign')->references('id')->on('OP_PRODUTO')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->dropForeign('OP_PRODUTO_PEDIDO_9140da64_foreign');
        });

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->dropColumn('PRODUTO');
        });
    }
};
