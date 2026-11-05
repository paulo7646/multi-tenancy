<?php

namespace App\Filament\Resources\Dicionario\ModuloResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\SalvaNoDicionario;
use App\Filament\Resources\Dicionario\ModuloResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModulo extends CreateRecord
{
    use SalvaNoDicionario;

    protected static string $resource = ModuloResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
