<?php

namespace App\Filament\Resources\Dicionario\MdStatusResource\Pages;

use App\Filament\Resources\Dicionario\MdStatusResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMdStatus extends ListRecords
{
    use TituloPadrao;

    protected static string $resource = MdStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
