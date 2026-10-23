<?php

namespace App\Filament\Resources\Dicionario\MdFormularioResource\Pages;

use App\Filament\Resources\Dicionario\MdFormularioResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMdFormularios extends ListRecords
{
    protected static string $resource = MdFormularioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
