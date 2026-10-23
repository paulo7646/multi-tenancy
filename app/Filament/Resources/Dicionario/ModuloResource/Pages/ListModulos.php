<?php

namespace App\Filament\Resources\Dicionario\ModuloResource\Pages;

use App\Filament\Resources\Dicionario\ModuloResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModulos extends ListRecords
{
    protected static string $resource = ModuloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
