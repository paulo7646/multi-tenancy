<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de plataforma: configurações da empresa. A mesma migration vale para o banco
 * central e para os clientes (database/migrations e database/migrations/tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plt_configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 60)->unique();
            $table->json('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['plt_configuracoes'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
