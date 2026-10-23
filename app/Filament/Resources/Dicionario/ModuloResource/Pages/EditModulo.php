<?php

namespace App\Filament\Resources\Dicionario\ModuloResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\ModuloResource;
use App\Sistema\Geradores\Gerador;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditModulo extends EditRecord
{
    use PublicaNoDicionario;

    protected static string $resource = ModuloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ModuloResource::excluir(DeleteAction::make()),
        ];
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarModulo($record);
    }
}
