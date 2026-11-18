<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastro de webservices (consumidos ou disponibilizados) e dos layouts de
 * integração. A mesma migration vale para o banco central e para os clientes
 * (database/migrations e database/migrations/tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webservices', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('tipo', 15)->default('consumir'); // consumir | disponibilizar
            $table->string('host')->nullable(); // https://teste.com
            $table->string('autenticacao', 20)->default('nenhuma'); // nenhuma | basic | bearer | apikey | oauth2
            $table->text('credenciais')->nullable(); // criptografado
            $table->json('headers')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // serviços do host: /usuario, /consulta, /consulta/teste
        Schema::create('ws_operacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webservice_id')->constrained('webservices')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('rota'); // /dadoprincipal/operacao/rota/aplicacao
            $table->string('metodo', 10)->default('GET');
            $table->string('formato', 10)->default('json'); // json | xml
            $table->unsignedSmallInteger('timeout')->default(30);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('ws_parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('ws_operacoes')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->text('valor')->nullable();
            $table->string('local', 10)->default('query'); // url | query | corpo | header
            $table->boolean('obrigatorio')->default(false);
            $table->timestamps();
        });

        // layout de EDI da operação
        Schema::create('ws_versoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('ws_operacoes')->cascadeOnDelete();
            $table->string('versao', 30);
            $table->date('inicio_vigencia')->nullable();
            $table->date('expira_em')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['operacao_id', 'versao']);
        });

        // árvore do retorno: root é o {} principal; os demais têm o nome que vem no JSON/XML
        Schema::create('ws_objetos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versao_id')->constrained('ws_versoes')->cascadeOnDelete();
            $table->foreignId('pai_id')->nullable()->constrained('ws_objetos')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->boolean('lista')->default(false); // o retorno traz vários itens deste objeto
            $table->boolean('obrigatorio')->default(true); // ausente no retorno = erro
            $table->boolean('novo_lote')->default(false); // cada item abre um lote (transação)
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });

        // tabela = nome_fisico da tabela do dicionário (no cliente ou na base pai)
        Schema::create('ws_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objeto_id')->constrained('ws_objetos')->cascadeOnDelete();
            $table->string('tipo', 30); // incluir | alterar | incluir_se_nao_existir | excluir | codigo_fonte
            $table->string('tabela', 60)->nullable();
            $table->string('funcao', 100)->nullable(); // codigo_fonte: método do controller da tabela
            $table->unsignedInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // campo de busca: localiza o registro (campo operador valor)
        Schema::create('ws_evento_busca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('ws_eventos')->cascadeOnDelete();
            $table->string('campo', 60);
            $table->string('operador', 10)->default('=');
            $table->string('valor')->nullable();
            $table->timestamps();
        });

        // atributo do retorno -> coluna da tabela do evento
        Schema::create('ws_atributos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('ws_eventos')->cascadeOnDelete();
            $table->string('nome_json', 100);
            $table->string('campo', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ws_atributos', 'ws_evento_busca', 'ws_eventos', 'ws_objetos', 'ws_versoes', 'ws_parametros', 'ws_operacoes', 'webservices'] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
