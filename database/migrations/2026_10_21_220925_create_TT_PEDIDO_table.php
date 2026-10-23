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
        Schema::create('TT_PEDIDO', function (Blueprint $table) {
            $table->id();
            $table->string('DESCRICAO', 255)->nullable(false);
            $table->unsignedBigInteger('STATUS_ID')->nullable();
            $table->string('CPF_CLIENTE', 14)->nullable();
            $table->dateTime('ENTREGA')->nullable();
            $table->string('INTERNO', 255)->nullable();
            $table->timestamps();
            $table->foreign('STATUS_ID', 'TT_PEDIDO_649e6425_foreign')->references('id')->on('TT_STATUS')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TT_PEDIDO');
    }
};
