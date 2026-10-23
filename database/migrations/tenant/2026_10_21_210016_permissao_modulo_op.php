<?php

// Gerado pelo dicionário de dados (modulos).

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permissoes::sincronizar('modulo_op', []);
    }

    public function down(): void
    {
        Permissoes::remover('modulo_op');
    }
};
