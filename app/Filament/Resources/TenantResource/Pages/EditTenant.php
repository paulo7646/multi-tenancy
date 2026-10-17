<?php

namespace App\Filament\Resources\TenantResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\TenantResource;
use App\Jobs\CreateTenantFrontend;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Slug preenchido agora (empresa antiga sem pasta): cria a pasta como no cadastro
        if ($this->record->wasChanged('slug') && filled($this->record->slug)) {
            CreateTenantFrontend::dispatchSync($this->record);
        }
    }
}
