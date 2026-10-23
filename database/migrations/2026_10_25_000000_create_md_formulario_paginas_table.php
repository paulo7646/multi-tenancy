<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tab pages dos formulários. Os componentes sem tab page ficam na tab "Geral".
 * Tipos: campos (da tabela), pai (campos da tabela pai, pela FK, só leitura),
 * tabela (lista de outra tabela ligada por campos, com incluir/alterar/excluir)
 * e customizada (conteúdo por código, no Resource).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $schema = Schema::connection('central');

        $schema->create('md_formulario_paginas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('md_formulario_id')->constrained('md_formularios')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('nome_fisico', 60);
            $table->string('tipo', 20)->default('campos');
            $table->unsignedInteger('ordem')->default(0);
            // pai: o campo FK desta tabela (uid) que aponta para a tabela pai
            $table->string('fk_campo', 36)->nullable();
            // tabela: destino e os campos de ligação (uids)
            $table->foreignId('tabela_id')->nullable()->constrained('md_tabelas')->nullOnDelete();
            $table->string('campo_tabela', 36)->nullable();
            $table->string('campo_ligacao', 36)->nullable();
            $table->timestamps();

            $table->unique(['md_formulario_id', 'nome_fisico']);
        });

        $schema->table('md_formulario_componentes', function (Blueprint $table) {
            // sem tab page: tab "Geral"
            $table->foreignId('md_formulario_pagina_id')->nullable()->after('md_formulario_id')
                ->constrained('md_formulario_paginas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('central');

        $schema->table('md_formulario_componentes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('md_formulario_pagina_id');
        });

        $schema->dropIfExists('md_formulario_paginas');
    }
};
