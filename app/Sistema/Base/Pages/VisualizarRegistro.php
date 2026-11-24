<?php

namespace App\Sistema\Base\Pages;

use App\Sistema\Base\BaseResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class VisualizarRegistro extends ViewRecord
{
    use TituloPadrao;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return static::getResource()::tela(BaseResource::VISUALIZAR, $this->getRecord())->antesDePreencher($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::botoesDoFormulario($this->getRecord(), $this),
            EditAction::make()->label('Alterar'),
        ];
    }
}
