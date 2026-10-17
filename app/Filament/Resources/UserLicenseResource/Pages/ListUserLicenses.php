<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\UserLicenseResource;
use Filament\Resources\Pages\ListRecords;

class ListUserLicenses extends ListRecords
{
    protected static string $resource = UserLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
