<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incluir/alterar/excluir agora são permissões do gerenciamento
 * (gerenciamento_{nome}_{ação}), marcadas por papel no Shield.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('md_gerenciamentos', function (Blueprint $table) {
            $table->dropColumn(['permite_incluir', 'permite_alterar', 'permite_excluir']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('md_gerenciamentos', function (Blueprint $table) {
            $table->boolean('permite_incluir')->default(true);
            $table->boolean('permite_alterar')->default(true);
            $table->boolean('permite_excluir')->default(true);
        });
    }
};
