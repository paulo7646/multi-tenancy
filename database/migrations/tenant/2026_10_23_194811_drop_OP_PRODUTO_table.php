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
        Schema::dropIfExists('OP_PRODUTO');
    }

    public function down(): void
    {
        Schema::create('OP_PRODUTO', function (Blueprint $table) {
            $table->id();
            $table->string('NOME', 255)->nullable();
            $table->decimal('VALOR', 10, 2)->nullable(false);
            $table->timestamps();
        });
    }
};
