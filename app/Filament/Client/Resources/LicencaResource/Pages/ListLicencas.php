<?php

namespace App\Filament\Client\Resources\LicencaResource\Pages;

use App\Filament\Client\Resources\LicencaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLicencas extends ListRecords
{
    protected static string $resource = LicencaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
