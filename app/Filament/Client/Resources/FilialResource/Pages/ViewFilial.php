<?php

namespace App\Filament\Client\Resources\FilialResource\Pages;

use App\Filament\Client\Resources\FilialResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFilial extends ViewRecord
{
    use TituloPadrao;

    protected static string $resource = FilialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
