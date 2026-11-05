<?php

namespace App\Plataforma\Resources\PastaResource\Pages;

use App\Plataforma\Resources\PastaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePastas extends ManageRecords
{
    protected static string $resource = PastaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
