<?php

namespace App\Filament\Resources\TenantResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\TenantResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
