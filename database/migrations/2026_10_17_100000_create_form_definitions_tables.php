<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        // Formulários do construtor (geram tabela, model, controller e tela)
        Schema::connection('central')->create('form_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // nome no singular: "Cliente"
            $table->string('title'); // título/menu no plural: "Clientes"
            $table->string('database'); // central | tenant
            $table->string('table_name');
            $table->string('model_class')->unique();
            $table->string('controller_class')->unique();
            $table->string('resource_class')->unique();
            $table->string('navigation_group')->nullable();
            $table->string('navigation_icon')->nullable();
            $table->string('lookup_field')->nullable(); // coluna exibida quando outro form aponta para este
            $table->json('roles')->nullable(); // papéis que recebem as permissões da tela
            $table->json('generated_schema')->nullable(); // campos como estavam na última migration gerada
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['database', 'table_name']);
        });

        Schema::connection('central')->create('form_fields', function (Blueprint $table) {
            $table->id();
            // identificador fixo do campo (vai para o JSON no git): renomear ou reimportar não perde o vínculo com a coluna
            $table->uuid('uid')->unique();
            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->string('name');
            $table->string('label');
            $table->string('type');
            $table->unsignedInteger('length')->nullable();
            $table->unsignedInteger('precision')->nullable();
            $table->unsignedInteger('scale')->nullable();
            $table->json('options')->nullable(); // valores do enum
            $table->boolean('nullable')->default(true);
            $table->boolean('required')->default(false);
            $table->boolean('unique')->default(false);
            $table->boolean('index')->default(false);
            $table->string('default')->nullable();
            $table->string('help')->nullable();
            $table->boolean('show_in_table')->default(true);
            $table->boolean('searchable')->default(false);
            $table->boolean('sortable')->default(false);
            $table->foreignId('lookup_form_id')->nullable()->constrained('form_definitions')->restrictOnDelete();
            $table->string('lookup_display')->nullable();
            $table->string('on_delete')->default('restrict');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['form_definition_id', 'name']);
        });

        Schema::connection('central')->create('form_buttons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->string('label');
            $table->string('method');
            $table->string('icon')->nullable();
            $table->string('color')->default('primary');
            $table->string('position')->default('record'); // record | header | bulk
            $table->string('action')->default('controller'); // controller | url
            $table->string('url')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::connection('central')->create('form_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
            $table->string('label');
            $table->string('field');
            $table->string('type'); // select | ternary | date_range | text
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('form_filters');
        Schema::connection('central')->dropIfExists('form_buttons');
        Schema::connection('central')->dropIfExists('form_fields');
        Schema::connection('central')->dropIfExists('form_definitions');
    }
};
