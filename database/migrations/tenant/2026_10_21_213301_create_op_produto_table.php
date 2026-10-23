<?php

// Gerado pelo dicionário de dados (md_tabelas).

use App\Sistema\Schema\MigrationGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('op_produto', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 255)->nullable(false);
            $table->decimal('valor', 10, 2)->nullable(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('op_produto');
    }
};
