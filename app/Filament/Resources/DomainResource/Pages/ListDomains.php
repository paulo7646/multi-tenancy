<?php

namespace App\Filament\Resources\DomainResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\DomainResource;
use Filament\Resources\Pages\ListRecords;

class ListDomains extends ListRecords
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
