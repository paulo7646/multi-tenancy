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
        MigrationGuard::emptyTable('OP_PRODUTO', 'VALOR');

        Schema::table('OP_PRODUTO', function (Blueprint $table) {
            $table->decimal('VALOR', 10, 2)->nullable(false);
        });
    }

    public function down(): void
    {
        Schema::table('OP_PRODUTO', function (Blueprint $table) {
            $table->dropColumn('VALOR');
        });
    }
};
