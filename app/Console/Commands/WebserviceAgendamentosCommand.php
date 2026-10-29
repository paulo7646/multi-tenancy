<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Webservice\Motor\Agendador;
use Illuminate\Console\Command;
use Throwable;

class WebserviceAgendamentosCommand extends Command
{
    protected $signature = 'webservice:agendamentos';

    protected $description = 'Roda os agendamentos de webservice que venceram (base pai e clientes)';

    public function handle(): int
    {
        $total = Agendador::rodarPendentes();

        foreach (Tenant::all() as $tenant) {
            try {
                $total += $tenant->run(fn () => Agendador::rodarPendentes());
            } catch (Throwable $e) {
                report($e);
                $this->error("{$tenant->displayName()}: {$e->getMessage()}");
            }
        }

        $this->info("{$total} agendamento(s) executado(s).");

        return self::SUCCESS;
    }
}
