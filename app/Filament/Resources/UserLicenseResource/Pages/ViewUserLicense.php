<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use App\Filament\Resources\UserLicenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserLicense extends ViewRecord
{
    protected static string $resource = UserLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
