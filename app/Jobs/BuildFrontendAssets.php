<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Roda "npm run build" para compilar os ambientes Vue de /views
 * (inclusive as pastas de empresas recém-criadas).
 */
class BuildFrontendAssets implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function middleware(): array
    {
        // Um build por vez; se outra empresa for criada durante o build,
        // o próximo espera e compila de novo já com a pasta nova.
        return [(new WithoutOverlapping('vite-build'))->releaseAfter(30)->expireAfter(660)];
    }

    public function handle(): void
    {
        // Com "npm run dev" rodando o Vite já serve as pastas novas.
        if (file_exists(public_path('hot'))) {
            return;
        }

        $result = Process::path(base_path())
            ->timeout($this->timeout)
            ->run([config('services.npm.binary'), 'run', 'build']);

        if ($result->failed()) {
            Log::error('Falha no build do frontend', [
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            $result->throw();
        }

        Log::info('Build do frontend concluído.');
    }
}
