<?php

namespace App\Filament\Client\Resources\FilialResource\Pages;

use App\Filament\Client\Resources\FilialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFiliais extends ListRecords
{
    protected static string $resource = FilialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
