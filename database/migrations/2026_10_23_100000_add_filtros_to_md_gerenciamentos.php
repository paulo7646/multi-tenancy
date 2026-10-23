<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filtros do datagrid: [{nome, campo: uid|situacao}]. O tipo do campo define
 * o filtro (FK = combo pela tradução de lookup, data = de/até...).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('md_gerenciamentos', function (Blueprint $table) {
            $table->json('filtros')->nullable()->after('colunas');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('md_gerenciamentos', function (Blueprint $table) {
            $table->dropColumn('filtros');
        });
    }
};
