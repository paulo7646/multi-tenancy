<?php

use App\Sistema\Schema\StatusNoTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cópia do md_status do banco central (mesmos ids): as tabelas do cliente que
 * controlam situação apontam para ela pela chave estrangeira MD_STATUS_ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('md_status', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('nome_fisico', 40)->unique();
            $table->string('icone')->nullable();
            $table->string('cor', 20)->default('gray');
            $table->string('descricao')->nullable();
            $table->timestamps();
        });

        StatusNoTenant::sincronizar();
    }

    public function down(): void
    {
        Schema::dropIfExists('md_status');
    }
};
