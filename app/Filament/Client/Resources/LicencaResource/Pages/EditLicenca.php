<?php

namespace App\Filament\Client\Resources\LicencaResource\Pages;

use App\Filament\Client\Resources\LicencaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLicenca extends EditRecord
{
    protected static string $resource = LicencaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
