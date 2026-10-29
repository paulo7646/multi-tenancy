<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Webservice\Motor\Retencao;
use Illuminate\Console\Command;
use Throwable;

class WebserviceLimparCommand extends Command
{
    protected $signature = 'webservice:limpar {--dias= : Dias de retenção (padrão: config webservice.retencao_dias)}';

    protected $description = 'Apaga integrações de webservice antigas e os arquivos delas (base pai e clientes)';

    public function handle(): int
    {
        $dias = $this->option('dias') !== null ? (int) $this->option('dias') : null;
        $total = Retencao::limpar($dias);

        foreach (Tenant::all() as $tenant) {
            try {
                $total += $tenant->run(fn () => Retencao::limpar($dias));
            } catch (Throwable $e) {
                report($e);
                $this->error("{$tenant->displayName()}: {$e->getMessage()}");
            }
        }

        $this->info("{$total} integração(ões) apagada(s).");

        return self::SUCCESS;
    }
}
