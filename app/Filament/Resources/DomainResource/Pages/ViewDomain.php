<?php

namespace App\Filament\Resources\DomainResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\DomainResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDomain extends ViewRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
