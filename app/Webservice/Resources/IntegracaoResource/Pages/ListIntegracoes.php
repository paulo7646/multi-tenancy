<?php

namespace App\Webservice\Resources\IntegracaoResource\Pages;

use App\Webservice\Resources\IntegracaoResource;
use Filament\Resources\Pages\ListRecords;

class ListIntegracoes extends ListRecords
{
    protected static string $resource = IntegracaoResource::class;
}
