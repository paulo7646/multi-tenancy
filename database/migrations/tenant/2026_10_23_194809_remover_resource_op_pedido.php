<?php

// Gerado pelo dicionário de dados (modulos).

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permissoes::remover(...['view_any_op_pedido', 'view_op_pedido', 'create_op_pedido', 'update_op_pedido', 'delete_op_pedido', 'delete_any_op_pedido']);
    }

    public function down(): void
    {
        Permissoes::criar(['view_any_op_pedido', 'view_op_pedido', 'create_op_pedido', 'update_op_pedido', 'delete_op_pedido', 'delete_any_op_pedido'], []);
    }
};
