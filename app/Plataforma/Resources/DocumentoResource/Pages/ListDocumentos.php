<?php

namespace App\Plataforma\Resources\DocumentoResource\Pages;

use App\Plataforma\Resources\DocumentoResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDocumentos extends ListRecords
{
    protected static string $resource = DocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviar')
                ->label('Enviar documento')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema(DocumentoResource::camposDeEnvio())
                ->action(function (array $data) {
                    if (DocumentoResource::enviar($data)) {
                        Notification::make()->title('Documento enviado')->success()->send();
                    }
                }),
        ];
    }
}
