<?php

namespace App\Filament\Client\Resources\UserResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Client\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
