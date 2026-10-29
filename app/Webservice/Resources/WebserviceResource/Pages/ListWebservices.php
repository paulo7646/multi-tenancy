<?php

namespace App\Webservice\Resources\WebserviceResource\Pages;

use App\Webservice\Resources\WebserviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebservices extends ListRecords
{
    protected static string $resource = WebserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
