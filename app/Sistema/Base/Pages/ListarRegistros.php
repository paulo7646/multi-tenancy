<?php

namespace App\Sistema\Base\Pages;

use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Listagem dos Resources do dicionário: botões do topo + Incluir.
 */
abstract class ListarRegistros extends ListRecords
{
    use TituloPadrao;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Incluir')->keyBindings(['f2']),
        ];
    }
}
