<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMdTabela extends ViewRecord
{
    use TituloPadrao;

    protected static string $resource = MdTabelaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
