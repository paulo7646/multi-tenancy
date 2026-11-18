<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Releases (pacotes de módulo) que este cliente já aplicou. O id da release é do banco pai (sem FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases_aplicadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('modulo', 5);
            $table->unsignedInteger('versao');
            $table->timestamp('aplicada_em');
            $table->string('aplicada_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases_aplicadas');
    }
};
