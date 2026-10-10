<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ClienteFrontend;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateTenantFrontend implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Tenant $tenant) {}

    public function handle(): void
    {
        if (blank($this->tenant->slug)) {
            return;
        }

        ClienteFrontend::scaffold($this->tenant->slug);

        BuildFrontendAssets::dispatch();
    }
}
