<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMdTabelas extends ListRecords
{
    use TituloPadrao;

    protected static string $resource = MdTabelaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdTabelaResource::acaoSchemaGeral(),
            CreateAction::make(),
        ];
    }
}
