<?php

namespace App\Filament\Client\Resources\SituacaoResource\Pages;

use App\Filament\Client\Resources\SituacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSituacaos extends ListRecords
{
    protected static string $resource = SituacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
