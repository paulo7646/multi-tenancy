<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use App\Filament\Resources\UserLicenseResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserLicenses extends ListRecords
{
    use TituloPadrao;

    protected static string $resource = UserLicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
