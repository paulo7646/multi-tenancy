<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Controller de regras de negócio de cada tabela (ControllerProduto.php).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('md_tabelas', function (Blueprint $table) {
            $table->string('nome_controller', 80)->nullable()->after('nome_fisico');
        });

        // tabelas já cadastradas: sugestão padrão (op_produto -> ControllerProduto)
        $prefixos = DB::connection('central')->table('modulos')->pluck('prefixo', 'id');

        foreach (DB::connection('central')->table('md_tabelas')->get() as $tabela) {
            $prefixo = $prefixos[$tabela->modulo_id] ?? '';
            $nome = str_starts_with($tabela->nome_fisico, "{$prefixo}_") ? substr($tabela->nome_fisico, strlen($prefixo) + 1) : $tabela->nome_fisico;

            DB::connection('central')->table('md_tabelas')->where('id', $tabela->id)
                ->update(['nome_controller' => 'Controller' . Str::studly($nome)]);
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('md_tabelas', function (Blueprint $table) {
            $table->dropColumn('nome_controller');
        });
    }
};
