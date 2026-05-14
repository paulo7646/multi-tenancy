<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use App\Filament\Resources\UserLicenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserLicenses extends ListRecords
{
    protected static string $resource = UserLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
