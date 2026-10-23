<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tab page do tipo Tabela "sem permissão": só lista (ninguém inclui, altera ou exclui por ali).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('md_formulario_paginas', function (Blueprint $table) {
            $table->boolean('sem_permissao')->default(false)->after('campo_ligacao');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('md_formulario_paginas', function (Blueprint $table) {
            $table->dropColumn('sem_permissao');
        });
    }
};
