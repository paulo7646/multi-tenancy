<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dicionário de dados (metadados). Fica no banco central e descreve as
 * tabelas, formulários e gerenciamentos (grids) da base pai e dos clientes.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $schema = Schema::connection('central');

        // Módulos: viram grupos no menu lateral; o prefixo inicia o nome físico das tabelas (op_usuario)
        $schema->create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('prefixo', 5)->unique();
            $table->string('pasta', 60)->unique(); // app/Modulos/{pasta}: fixa depois de criada
            $table->text('descricao')->nullable();
            $table->string('icone')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->json('roles')->nullable(); // papéis que acessam o módulo
            $table->json('roles_gerados')->nullable(); // papéis da última migration de permissão gerada
            $table->timestamps();
        });

        // Situações disponíveis para as tabelas que controlam situação
        $schema->create('md_status', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('nome_fisico', 40)->unique();
            $table->string('icone')->nullable();
            $table->string('cor', 20)->default('gray');
            $table->string('descricao')->nullable();
            $table->timestamps();
        });

        $schema->create('md_tabelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modulo_id')->constrained('modulos')->restrictOnDelete();
            $table->string('nome', 60);
            $table->string('nome_fisico', 60);
            $table->text('descricao')->nullable();
            $table->string('banco', 10); // central (base pai) | tenant (clientes)
            $table->boolean('controla_situacao')->default(false);
            $table->string('campo_exibicao', 60)->nullable(); // coluna mostrada quando outra tabela aponta para esta
            $table->json('generated_schema')->nullable(); // estrutura da última migration gerada
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            // único em todos os bancos: a pasta app/Modulos/{modulo}/{nome_fisico} é uma só
            $table->unique('nome_fisico');
        });

        // Situações (md_status) que valem para cada tabela
        $schema->create('md_tabela_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_tabela_id')->constrained('md_tabelas')->cascadeOnDelete();
            $table->foreignId('md_status_id')->constrained('md_status')->restrictOnDelete();
            $table->boolean('padrao')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['md_tabela_id', 'md_status_id']);
        });

        $schema->create('md_campos', function (Blueprint $table) {
            $table->id();
            // fixo: renomear o campo vira renameColumn e o vínculo com o layout dos formulários não se perde
            $table->uuid('uid')->unique();
            $table->foreignId('md_tabela_id')->constrained('md_tabelas')->cascadeOnDelete();
            $table->string('nome', 60); // título no formulário
            $table->string('nome_fisico', 60); // coluna
            $table->string('tipo', 30);
            $table->unsignedInteger('tamanho')->nullable();
            $table->unsignedInteger('precisao')->nullable();
            $table->unsignedInteger('escala')->nullable();
            $table->json('opcoes')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->boolean('unico')->default(false);
            $table->boolean('indice')->default(false);
            $table->string('padrao')->nullable();
            $table->string('ajuda')->nullable();
            $table->foreignId('fk_tabela_id')->nullable()->constrained('md_tabelas')->restrictOnDelete();
            $table->string('fk_on_delete', 10)->default('restrict');
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['md_tabela_id', 'nome_fisico']);
        });

        $schema->create('md_formularios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_tabela_id')->constrained('md_tabelas')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('titulo', 100);
            $table->string('nome_fisico', 60)->unique();
            $table->string('nome_arquivo', 80); // classe/arquivo: ViewProduto.php
            $table->boolean('paginado')->default(false);
            $table->unsignedTinyInteger('colunas')->default(2);
            $table->json('layout')->nullable(); // [{titulo, campos: [{campo, colunas}]}]
            $table->timestamps();

            $table->unique(['md_tabela_id', 'nome_arquivo']);
        });

        $schema->create('md_formulario_botoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_formulario_id')->constrained('md_formularios')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('titulo', 60);
            $table->string('nome_fisico', 60); // método na classe de botões
            $table->string('nome_arquivo', 80); // classe/arquivo: BotoesProduto.php
            $table->string('icone')->nullable();
            $table->string('cor', 20)->default('primary');
            $table->boolean('confirmar')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['md_formulario_id', 'nome_fisico']);
        });

        // Gerenciamento: o datagrid inicial da tabela (item do menu dentro do módulo)
        $schema->create('md_gerenciamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_tabela_id')->constrained('md_tabelas')->cascadeOnDelete();
            $table->foreignId('md_formulario_id')->nullable()->constrained('md_formularios')->nullOnDelete();
            $table->string('nome', 60);
            $table->string('titulo', 100);
            $table->string('nome_fisico', 60)->unique(); // slug da tela
            $table->string('nome_arquivo', 80); // classe/arquivo: GerenciamentoProduto.php
            $table->string('icone')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->json('colunas')->nullable(); // [{campo, pesquisavel, ordenavel, oculta}]
            $table->boolean('permite_incluir')->default(true);
            $table->boolean('permite_alterar')->default(true);
            $table->boolean('permite_excluir')->default(true);
            $table->timestamps();

            $table->unique(['md_tabela_id', 'nome_arquivo']);
        });

        $schema->create('md_gerenciamento_botoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_gerenciamento_id')->constrained('md_gerenciamentos')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('titulo', 60);
            $table->string('nome_fisico', 60); // método na classe de botões
            $table->string('nome_arquivo', 80); // classe/arquivo: BotoesProduto.php
            $table->string('icone')->nullable();
            $table->string('cor', 20)->default('primary');
            $table->boolean('no_registro')->default(true); // true: coluna de ações; false: topo do datagrid
            $table->boolean('exige_selecao')->default(false); // só no topo: precisa selecionar registros
            $table->boolean('multiplos')->default(false); // aceita mais de um registro selecionado
            $table->boolean('confirmar')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['md_gerenciamento_id', 'nome_fisico']);
        });

        DB::connection('central')->table('md_status')->insert(collect([
            ['Ativo', 'ativo', 'heroicon-o-check-circle', 'green'],
            ['Inativo', 'inativo', 'heroicon-o-x-circle', 'red'],
            ['Pendente', 'pendente', 'heroicon-o-clock', 'amber'],
            ['Em andamento', 'em_andamento', 'heroicon-o-arrow-path', 'blue'],
            ['Concluído', 'concluido', 'heroicon-o-check-badge', 'emerald'],
            ['Cancelado', 'cancelado', 'heroicon-o-no-symbol', 'gray'],
        ])->map(fn (array $s) => [
            'nome' => $s[0], 'nome_fisico' => $s[1], 'icone' => $s[2], 'cor' => $s[3],
            'created_at' => now(), 'updated_at' => now(),
        ])->all());
    }

    public function down(): void
    {
        $schema = Schema::connection('central');

        foreach (['md_gerenciamento_botoes', 'md_gerenciamentos', 'md_formulario_botoes', 'md_formularios',
            'md_campos', 'md_tabela_status', 'md_tabelas', 'md_status', 'modulos'] as $table) {
            $schema->dropIfExists($table);
        }
    }
};
