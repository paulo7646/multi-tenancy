<?php

namespace App\Modulos\Operacional\OP_PRODUTO_PEDIDO\ProdutoPedidoResource\Pages;

use App\Sistema\Base\Pages\ListarRegistros;
use App\Modulos\Operacional\OP_PRODUTO_PEDIDO\ProdutoPedidoResource;

class Listar extends ListarRegistros
{
    protected static string $resource = ProdutoPedidoResource::class;
}
