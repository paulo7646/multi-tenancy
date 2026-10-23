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
        Schema::dropIfExists('OP_PEDIDO');
    }

    public function down(): void
    {
        Schema::create('OP_PEDIDO', function (Blueprint $table) {
            $table->id();
            $table->string('NOME', 255)->nullable(false);
            $table->string('NUMERO', 255)->nullable(false);
            $table->timestamps();
            $table->unique('NUMERO', 'OP_PEDIDO_94de1c1e_unique');
        });
    }
};
