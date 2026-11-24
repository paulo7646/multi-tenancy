<?php

namespace App\Webservice\Resources\IntegracaoResource\Pages;

use App\Sistema\Base\Concerns\TituloPadrao;
use App\Webservice\Resources\IntegracaoResource;
use Filament\Resources\Pages\ViewRecord;

class ViewIntegracao extends ViewRecord
{
    use TituloPadrao;

    protected static string $resource = IntegracaoResource::class;
}
