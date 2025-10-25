<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('situacaos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        DB::table('situacaos')->insert([
            'nome' => 'Ativo',
            'icon' => '',
            'color' => '',
        ]);

        DB::table('situacaos')->insert([
            'nome' => 'Inativo',
            'icon' => '',
            'color' => '',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('situacaos');
    }
};
