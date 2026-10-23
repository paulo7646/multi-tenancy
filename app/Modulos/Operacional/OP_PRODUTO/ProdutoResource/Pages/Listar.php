<?php

namespace App\Modulos\Operacional\OP_PRODUTO\ProdutoResource\Pages;

use App\Sistema\Base\Pages\ListarRegistros;
use App\Modulos\Operacional\OP_PRODUTO\ProdutoResource;

class Listar extends ListarRegistros
{
    protected static string $resource = ProdutoResource::class;
}
