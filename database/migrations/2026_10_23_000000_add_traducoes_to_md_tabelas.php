<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traduções da tabela: os campos (uids, em ordem) que representam um
 * registro quando outra tabela aponta para ela (FK):
 *  - traducao_tabela: nas colunas dos gerenciamentos (datagrid);
 *  - traducao_lookup: nos campos dos formulários (select da FK).
 * Ex.: [codigo, nome] mostra "001 - Bebidas".
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('md_tabelas', function (Blueprint $table) {
            $table->json('traducao_tabela')->nullable()->after('campo_exibicao');
            $table->json('traducao_lookup')->nullable()->after('traducao_tabela');
        });

        // o campo de exibição de antes vira as duas traduções
        foreach (DB::connection('central')->table('md_tabelas')->whereNotNull('campo_exibicao')->get() as $tabela) {
            $uid = DB::connection('central')->table('md_campos')
                ->where('md_tabela_id', $tabela->id)
                ->where('nome_fisico', $tabela->campo_exibicao)
                ->value('uid');

            if ($uid) {
                DB::connection('central')->table('md_tabelas')->where('id', $tabela->id)->update([
                    'traducao_tabela' => json_encode([$uid]),
                    'traducao_lookup' => json_encode([$uid]),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('md_tabelas', function (Blueprint $table) {
            $table->dropColumn(['traducao_tabela', 'traducao_lookup']);
        });
    }
};
