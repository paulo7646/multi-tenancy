<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Máscara do componente (formato do Filament: 9 = número, a = letra, * = qualquer;
 * "$dinheiro" = valor com milhar e decimais).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('md_formulario_componentes', function (Blueprint $table) {
            $table->string('mascara', 60)->nullable()->after('componente');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('md_formulario_componentes', function (Blueprint $table) {
            $table->dropColumn('mascara');
        });
    }
};
