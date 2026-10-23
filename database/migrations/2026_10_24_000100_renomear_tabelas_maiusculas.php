<?php

// Tabelas do dicionário em maiúscula (op_produto -> OP_PRODUTO) neste banco.

use App\Sistema\Schema\RenomearMaiusculas;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RenomearMaiusculas::executar('central');
    }

    public function down(): void
    {
        // sem volta: os nomes em maiúscula valem daqui em diante
    }
};
