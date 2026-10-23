<?php

namespace App\Sistema\Base\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Listagem dos Resources do dicionário: botões do topo + Incluir.
 */
abstract class ListarRegistros extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::botoesDoTopo($this),
            CreateAction::make()->label('Incluir')->keyBindings(['f2']),
        ];
    }
}
