<?php

namespace App\Filament\Resources\Dicionario\MdStatusResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdStatusResource;
use App\Sistema\Geradores\Gerador;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMdStatus extends CreateRecord
{
    use PublicaNoDicionario;

    protected static string $resource = MdStatusResource::class;

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarStatus();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
