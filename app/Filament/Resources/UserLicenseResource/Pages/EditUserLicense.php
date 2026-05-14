<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use App\Filament\Resources\UserLicenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserLicense extends EditRecord
{
    protected static string $resource = UserLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
