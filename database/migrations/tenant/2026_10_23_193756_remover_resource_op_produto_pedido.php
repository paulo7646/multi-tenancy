<?php

// Gerado pelo dicionário de dados (modulos).

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permissoes::remover(...['view_any_op_produto_pedido', 'view_op_produto_pedido', 'create_op_produto_pedido', 'update_op_produto_pedido', 'delete_op_produto_pedido', 'delete_any_op_produto_pedido']);
    }

    public function down(): void
    {
        Permissoes::criar(['view_any_op_produto_pedido', 'view_op_produto_pedido', 'create_op_produto_pedido', 'update_op_produto_pedido', 'delete_op_produto_pedido', 'delete_any_op_produto_pedido'], []);
    }
};
