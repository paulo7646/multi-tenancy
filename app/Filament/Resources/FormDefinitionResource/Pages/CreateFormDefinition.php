<?php

namespace App\Filament\Resources\FormDefinitionResource\Pages;

use App\Filament\Resources\FormDefinitionResource;
use App\Filament\Resources\FormDefinitionResource\Pages\Concerns\GeneratesForm;
use App\Models\FormDefinition;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateFormDefinition extends CreateRecord
{
    use GeneratesForm;

    protected static string $resource = FormDefinitionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->checkForm(new FormDefinition($data), $data);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->generateForm();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // a notificação da geração já informa
    }
}
