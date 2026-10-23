<?php

namespace App\Sistema\Base\Pages;

use App\Sistema\Base\BaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

abstract class CriarRegistro extends CreateRecord
{
    use GravaNoDicionario;

    protected function getOperacaoFilament(): string
    {
        return 'create';
    }

    protected function afterFill(): void
    {
        // valores padrão já aplicados: a personalização pode ajustar
        $this->form->fill(static::getResource()::tela(BaseResource::INCLUIR)->antesDePreencher($this->form->getRawState()));
    }

    protected function handleRecordCreation(array $data): Model
    {
        return $this->gravar(new ($this->getModel()), $data, BaseResource::INCLUIR);
    }

    protected function getHeaderActions(): array
    {
        return static::getResource()::botoesDoFormulario(null, $this);
    }

    protected function getRedirectUrl(): string
    {
        $resource = static::getResource();

        return $resource::hasPage('view') ? $resource::getUrl('view', ['record' => $this->getRecord()]) : $resource::getUrl('index');
    }
}
