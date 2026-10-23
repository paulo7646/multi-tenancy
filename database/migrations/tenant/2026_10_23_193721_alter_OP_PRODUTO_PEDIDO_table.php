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
        MigrationGuard::references('OP_PRODUTO_PEDIDO', 'PEDIDO', 'OP_PEDIDO');

        MigrationGuard::references('OP_PRODUTO_PEDIDO', 'PRODUTO', 'OP_PRODUTO');

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->dropForeign('OP_PRODUTO_PEDIDO_8ef46fe9_foreign');
            $table->dropForeign('OP_PRODUTO_PEDIDO_9140da64_foreign');
        });

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->foreign('PEDIDO', 'OP_PRODUTO_PEDIDO_8ef46fe9_foreign')->references('id')->on('OP_PEDIDO')->cascadeOnDelete();
            $table->foreign('PRODUTO', 'OP_PRODUTO_PEDIDO_9140da64_foreign')->references('id')->on('OP_PRODUTO')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        MigrationGuard::references('OP_PRODUTO_PEDIDO', 'PEDIDO', 'OP_PEDIDO');

        MigrationGuard::references('OP_PRODUTO_PEDIDO', 'PRODUTO', 'OP_PRODUTO');

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->dropForeign('OP_PRODUTO_PEDIDO_8ef46fe9_foreign');
            $table->dropForeign('OP_PRODUTO_PEDIDO_9140da64_foreign');
        });

        Schema::table('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->foreign('PEDIDO', 'OP_PRODUTO_PEDIDO_8ef46fe9_foreign')->references('id')->on('OP_PEDIDO')->restrictOnDelete();
            $table->foreign('PRODUTO', 'OP_PRODUTO_PEDIDO_9140da64_foreign')->references('id')->on('OP_PRODUTO')->restrictOnDelete();
        });
    }
};
