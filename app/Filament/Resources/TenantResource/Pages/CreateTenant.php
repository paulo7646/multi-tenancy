<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // o administrador da empresa vem do formulário; a senha nunca fica em texto puro
        $data['name'] = 'admin';
        $data['email'] = $data['admin_email'];
        $data['password'] = Hash::make($data['admin_password']);
        unset($data['admin_email'], $data['admin_password']);

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
