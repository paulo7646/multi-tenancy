<?php

namespace App\Filament\Resources\Dicionario\MdGerenciamentoResource\Pages;

use App\Filament\Resources\Dicionario\MdGerenciamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMdGerenciamentos extends ListRecords
{
    protected static string $resource = MdGerenciamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
