<?php

// Gerado pelo dicionário de dados (modulos).

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permissoes::criar(['view_any_te_cliente', 'view_te_cliente', 'create_te_cliente', 'update_te_cliente', 'delete_te_cliente', 'delete_any_te_cliente'], []);
    }

    public function down(): void
    {
        Permissoes::remover(...['view_any_te_cliente', 'view_te_cliente', 'create_te_cliente', 'update_te_cliente', 'delete_te_cliente', 'delete_any_te_cliente']);
    }
};
