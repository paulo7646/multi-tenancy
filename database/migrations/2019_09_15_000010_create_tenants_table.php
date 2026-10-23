<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            // pasta do frontend da empresa (views/clientes/{slug})
            $table->string('slug')->nullable()->unique();

            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            // limite de licenças ativas; null = sem limite
            $table->unsignedInteger('qtd_licencas')->nullable();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
