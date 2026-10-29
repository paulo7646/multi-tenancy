<?php

namespace App\Webservice\Resources\OperacaoResource\Pages;

use App\Webservice\Resources\EditFilho;
use App\Webservice\Resources\OperacaoResource;

class EditOperacao extends EditFilho
{
    protected static string $resource = OperacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [OperacaoResource::acaoExecutar(), ...parent::getHeaderActions()];
    }
}
