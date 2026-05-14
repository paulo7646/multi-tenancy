<?php

namespace App\Filament\Client\Resources\LicencaResource\Pages;

use App\Filament\Client\Resources\LicencaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLicenca extends ViewRecord
{
    protected static string $resource = LicencaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
