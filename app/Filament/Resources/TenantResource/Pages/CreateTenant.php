<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['name'] = 'admin';
        $data['email'] = 'admin@gmail.com';
        $data['password'] = 'admin@123';
        return $data;
    }

    protected function afterCreate(): void
    {
        $tenan = $this->getRecord();
        $tenan->domains()->create(
            [
                'domain' => $this->data['domain'],
            ]
        );
    }
}
