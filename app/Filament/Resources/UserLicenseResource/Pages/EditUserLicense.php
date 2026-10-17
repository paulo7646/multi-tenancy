<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\UserLicenseResource;
use Filament\Resources\Pages\EditRecord;

class EditUserLicense extends EditRecord
{
    protected static string $resource = UserLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
