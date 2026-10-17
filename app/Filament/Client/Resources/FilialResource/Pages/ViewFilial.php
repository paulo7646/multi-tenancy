<?php

namespace App\Filament\Client\Resources\FilialResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Client\Resources\FilialResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFilial extends ViewRecord
{
    protected static string $resource = FilialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
