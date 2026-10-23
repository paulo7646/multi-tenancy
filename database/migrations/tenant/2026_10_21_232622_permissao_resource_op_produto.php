<?php

// Gerado pelo dicionário de dados (modulos).

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permissoes::criar(['view_any_op_produto', 'view_op_produto', 'create_op_produto', 'update_op_produto', 'delete_op_produto', 'delete_any_op_produto'], []);
    }

    public function down(): void
    {
        Permissoes::remover(...['view_any_op_produto', 'view_op_produto', 'create_op_produto', 'update_op_produto', 'delete_op_produto', 'delete_any_op_produto']);
    }
};
