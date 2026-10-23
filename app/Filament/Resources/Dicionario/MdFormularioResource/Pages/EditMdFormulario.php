<?php

namespace App\Filament\Resources\Dicionario\MdFormularioResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdFormularioResource\Pages\Concerns\DesenhaPrevia;
use App\Filament\Resources\Dicionario\MdFormularioResource;
use App\Sistema\Geradores\Gerador;
use App\Sistema\Runtime\Layout;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMdFormulario extends EditRecord
{
    use DesenhaPrevia;
    use PublicaNoDicionario;

    protected static string $resource = MdFormularioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdFormularioResource::previa(),
            MdFormularioResource::excluir(DeleteAction::make()),
        ];
    }

    protected function antesDePublicar(Model $record): void
    {
        // os campos já saíram dos grupos ao gravar a aba Componentes (grupos ficam para depois)
        $record->componentes()->where('tipo', Layout::GRUPO)->delete();
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarFormulario($record);
    }
}
