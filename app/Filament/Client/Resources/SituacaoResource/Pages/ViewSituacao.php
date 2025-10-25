<?php

namespace App\Filament\Client\Resources\SituacaoResource\Pages;

use App\Filament\Client\Resources\SituacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSituacao extends ViewRecord
{
    protected static string $resource = SituacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
