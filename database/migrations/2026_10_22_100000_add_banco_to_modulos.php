<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do módulo: base pai (central) ou clientes (tenant). As tabelas do
 * módulo ficam nesse banco e o módulo aparece no menu do painel dele.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('modulos', function (Blueprint $table) {
            $table->string('banco', 10)->default('tenant')->after('pasta');
        });

        // módulos com tabelas: o banco delas
        foreach (DB::connection('central')->table('md_tabelas')->select('modulo_id', 'banco')->distinct()->get() as $linha) {
            DB::connection('central')->table('modulos')->where('id', $linha->modulo_id)->update(['banco' => $linha->banco]);
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('modulos', function (Blueprint $table) {
            $table->dropColumn('banco');
        });
    }
};
