<?php

namespace App\Webservice\Resources\AgendamentoResource\Pages;

use App\Webservice\Resources\AgendamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgendamentos extends ListRecords
{
    protected static string $resource = AgendamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
