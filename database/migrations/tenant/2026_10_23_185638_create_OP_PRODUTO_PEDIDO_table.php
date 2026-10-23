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
        Schema::create('OP_PRODUTO_PEDIDO', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('PEDIDO')->nullable(false);
            $table->timestamps();
            $table->unique('PEDIDO', 'OP_PRODUTO_PEDIDO_8ef46fe9_unique');
            $table->foreign('PEDIDO', 'OP_PRODUTO_PEDIDO_8ef46fe9_foreign')->references('id')->on('OP_PEDIDO')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OP_PRODUTO_PEDIDO');
    }
};
