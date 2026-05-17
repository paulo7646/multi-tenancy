<?php

namespace App\Filament\Client\Resources\FilialResource\Pages;

use App\Filament\Client\Resources\FilialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFilial extends EditRecord
{
    protected static string $resource = FilialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
