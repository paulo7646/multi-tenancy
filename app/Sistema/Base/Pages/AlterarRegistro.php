<?php

namespace App\Sistema\Base\Pages;

use App\Sistema\Base\BaseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

abstract class AlterarRegistro extends EditRecord
{
    use GravaNoDicionario;

    protected function getOperacaoFilament(): string
    {
        return 'edit';
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->gravar($record, $data, BaseResource::ALTERAR);
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();

        return [
            ...$resource::botoesDoFormulario($this->getRecord(), $this),
            ViewAction::make(),
            DeleteAction::make()->using(fn (Model $record) => $resource::excluir(collect([$record])) > 0),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
