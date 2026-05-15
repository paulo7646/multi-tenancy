<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Models\UserLicense;

class UserLicenseObserver
{
    public function saved(UserLicense $license): void
    {
        if (! $license->tenant_id) {
            return;
        }

        if (! $license->wasChanged('status') && ! $license->wasChanged('expires_at')) {
            return;
        }

        $tenant = Tenant::find($license->tenant_id);
        if (! $tenant) {
            return;
        }

        tenancy()->initialize($tenant);

        try {
            $licencaName = $license->isActive() ? 'Licença Ativa' : 'Licença Suspensa';
            $licencaId = \App\Models\Licenca::where('nome', $licencaName)->value('id');

            if ($licencaId) {
                \App\Models\User::where('email', $license->user_email)
                    ->update(['licenca_id' => $licencaId]);
            }
        } finally {
            tenancy()->end();
        }
    }
}
