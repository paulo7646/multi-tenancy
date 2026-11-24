<?php

namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDomain extends ViewRecord
{
    use TituloPadrao;

    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
