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
        Schema::create('OP_PEDIDO_PRODUTO', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('PEDIDO')->nullable();
            $table->unsignedBigInteger('PRODUTO')->nullable();
            $table->timestamps();
            $table->foreign('PEDIDO', 'OP_PEDIDO_PRODUTO_216114df_foreign')->references('id')->on('OP_PEDIDO')->cascadeOnDelete();
            $table->foreign('PRODUTO', 'OP_PEDIDO_PRODUTO_9ef4ee09_foreign')->references('id')->on('OP_PRODUTO')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OP_PEDIDO_PRODUTO');
    }
};
