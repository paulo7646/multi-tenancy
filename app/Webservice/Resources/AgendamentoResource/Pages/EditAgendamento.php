<?php

namespace App\Webservice\Resources\AgendamentoResource\Pages;

use App\Webservice\Resources\AgendamentoResource;
use App\Webservice\Resources\AgendamentoResource\Pages\Concerns\ValidaParametros;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgendamento extends EditRecord
{
    use ValidaParametros;

    protected static string $resource = AgendamentoResource::class;

    protected function beforeSave(): void
    {
        $this->validarParametrosObrigatorios();
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
