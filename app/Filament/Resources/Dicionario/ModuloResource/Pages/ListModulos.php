<?php

namespace App\Filament\Resources\Dicionario\ModuloResource\Pages;

use App\Filament\Resources\Dicionario\ModuloResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModulos extends ListRecords
{
    use TituloPadrao;

    protected static string $resource = ModuloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
