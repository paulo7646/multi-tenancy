<?php

namespace App\Modulos\Operacional\OP_PEDIDO\PedidoResource\Pages;

use App\Sistema\Base\Pages\ListarRegistros;
use App\Modulos\Operacional\OP_PEDIDO\PedidoResource;

class Listar extends ListarRegistros
{
    protected static string $resource = PedidoResource::class;
}
