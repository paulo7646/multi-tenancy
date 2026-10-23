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
        Schema::create('TT_STATUS', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('CODIGO')->nullable(false);
            $table->string('DESCRICAO', 100)->nullable(false);
            $table->timestamps();
            $table->unique('CODIGO', 'TT_STATUS_d7881b44_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TT_STATUS');
    }
};
