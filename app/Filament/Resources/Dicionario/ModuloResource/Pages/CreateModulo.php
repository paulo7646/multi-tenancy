<?php

namespace App\Filament\Resources\Dicionario\ModuloResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\ModuloResource;
use App\Sistema\Geradores\Gerador;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateModulo extends CreateRecord
{
    use PublicaNoDicionario;

    protected static string $resource = ModuloResource::class;

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarModulo($record);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
