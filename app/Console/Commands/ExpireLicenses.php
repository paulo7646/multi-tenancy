<?php

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\Licenca;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLicense;
use Illuminate\Console\Command;

class ExpireLicenses extends Command
{
    protected $signature = 'licenses:expire';
    protected $description = 'Mark expired licenses as inactive and sync to tenant databases';

    public function handle(): int
    {
        $expired = UserLicense::where('status', LicenseStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $license) {
            $license->update(['status' => LicenseStatus::Inactive]);

            if (! $license->tenant_id) {
                continue;
            }

            $tenant = Tenant::find($license->tenant_id);
            if (! $tenant) {
                continue;
            }

            tenancy()->initialize($tenant);

            try {
                $licencaId = Licenca::where('nome', 'Licença Vencida')->value('id');

                if ($licencaId) {
                    User::where('email', $license->user_email)
                        ->update(['licenca_id' => $licencaId]);
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Processed {$expired->count()} expired license(s).");

        return self::SUCCESS;
    }
}
