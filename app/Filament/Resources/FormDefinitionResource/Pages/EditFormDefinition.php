<?php

namespace App\Filament\Resources\FormDefinitionResource\Pages;

use App\Filament\Resources\FormDefinitionResource;
use App\Filament\Resources\FormDefinitionResource\Pages\Concerns\GeneratesForm;
use App\Forms\Enums\FormConnection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFormDefinition extends EditRecord
{
    use GeneratesForm;

    protected static string $resource = FormDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrir')
                ->label('Abrir tela')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                // telas do banco das empresas abrem no painel de cada empresa
                ->visible(fn () => $this->record->database === FormConnection::Central
                    && $this->record->isGenerated()
                    && class_exists($this->record->resourceFqcn()))
                ->url(fn () => $this->record->resourceFqcn()::getUrl('index', panel: 'admin')),
            FormDefinitionResource::deleteAction(),
        ];
    }

    /**
     * Roda depois da validação e antes de gravar qualquer coisa (o getState
     * da edição já salva os repeaters de relacionamento, então não dá para
     * deixar para o mutateFormDataBeforeSave).
     */
    protected function beforeSave(): void
    {
        $data = collect($this->data)->only($this->record->getFillable())->all();

        $this->checkForm((clone $this->record)->fill($data), $data);
    }

    protected function afterSave(): void
    {
        $this->generateForm();
    }

    protected function getSavedNotification(): ?Notification
    {
        return null; // a notificação da geração já informa
    }
}
