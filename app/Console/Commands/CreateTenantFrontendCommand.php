<?php

namespace App\Console\Commands;

use App\Jobs\BuildFrontendAssets;
use App\Models\Tenant;
use App\Services\ClienteFrontend;
use Illuminate\Console\Command;

class CreateTenantFrontendCommand extends Command
{
    protected $signature = 'tenants:frontend {slug? : Slug da empresa (vazio = todas)}';

    protected $description = 'Cria a pasta Vue em views/clientes/{slug} para empresas que ainda não têm';

    public function handle(): int
    {
        $slugs = $this->argument('slug')
            ? [$this->argument('slug')]
            : Tenant::query()->whereNotNull('slug')->pluck('slug')->all();

        foreach ($slugs as $slug) {
            ClienteFrontend::scaffold($slug);
            $this->info("views/clientes/{$slug} pronto.");
        }

        BuildFrontendAssets::dispatch();
        $this->comment('Build do frontend enviado para a fila.');

        return self::SUCCESS;
    }
}
