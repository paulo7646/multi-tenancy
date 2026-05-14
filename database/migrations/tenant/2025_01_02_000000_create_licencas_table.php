<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        DB::table('licencas')->insert([
            ['nome' => 'Sem Licença', 'color' => 'gray', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Licença Ativa', 'color' => 'success', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Licença Suspensa', 'color' => 'warning', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Licença Vencida', 'color' => 'danger', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('licencas');
    }
};
