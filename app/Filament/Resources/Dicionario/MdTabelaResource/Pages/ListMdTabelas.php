<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\MdTabelaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMdTabelas extends ListRecords
{
    protected static string $resource = MdTabelaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
