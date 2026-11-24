<?php

namespace App\Modulos\Teste\TE_CLIENTE\ClienteResource\Pages;

use App\Modulos\Teste\TE_CLIENTE\ClienteResource;
use App\Sistema\Base\Pages\ListarRegistros;

class Listar extends ListarRegistros
{
    protected static string $resource = ClienteResource::class;
}
