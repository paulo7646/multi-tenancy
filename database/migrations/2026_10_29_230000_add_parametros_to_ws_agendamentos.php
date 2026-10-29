<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // valores dos parâmetros sem valor fixo na operação (nome => valor)
        Schema::table('ws_agendamentos', function (Blueprint $table) {
            $table->json('parametros')->nullable()->after('operacao_id');
        });
    }

    public function down(): void
    {
        Schema::table('ws_agendamentos', fn (Blueprint $table) => $table->dropColumn('parametros'));
    }
};
