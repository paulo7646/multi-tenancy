<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // o administrador da empresa vem do formulário. A senha segue em texto: o model Tenant
        // aplica o hash ao gravar (setPasswordAttribute); fazer o hash aqui também gravaria um
        // hash de hash e a senha digitada nunca mais conferiria.
        $data['name'] = 'admin';
        $data['email'] = $data['admin_email'];
        $data['password'] = $data['admin_password'];
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
