<?php

namespace App\Webservice\Resources\WebserviceResource\Pages;

use App\Sistema\Base\Concerns\TituloPadrao;
use App\Webservice\Resources\WebserviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebservices extends ListRecords
{
    use TituloPadrao;

    protected static string $resource = WebserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
