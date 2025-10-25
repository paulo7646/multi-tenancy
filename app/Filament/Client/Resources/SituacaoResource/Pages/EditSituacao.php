<?php

namespace App\Filament\Client\Resources\SituacaoResource\Pages;

use App\Filament\Client\Resources\SituacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSituacao extends EditRecord
{
    protected static string $resource = SituacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
