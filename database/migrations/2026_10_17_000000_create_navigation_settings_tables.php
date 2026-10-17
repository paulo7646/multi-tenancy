<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        // Ordem dos grupos do menu lateral, por painel (admin, client)
        Schema::connection('central')->create('navigation_groups', function (Blueprint $table) {
            $table->id();
            $table->string('panel');
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['panel', 'name']);
        });

        // Ajustes de cada item do menu; "item" é a classe da página/resource
        Schema::connection('central')->create('navigation_items', function (Blueprint $table) {
            $table->id();
            $table->string('panel');
            $table->string('item');
            $table->string('group')->nullable(); // null = sem grupo
            $table->string('label')->nullable(); // null = nome padrão
            $table->string('icon')->nullable(); // null = ícone padrão
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->unique(['panel', 'item']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('navigation_items');
        Schema::connection('central')->dropIfExists('navigation_groups');
    }
};
