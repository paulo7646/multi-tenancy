<?php

namespace App\Jobs;

use App\Sistema\AtualizadorDeClientes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Roda as migrations pendentes de todos os clientes em segundo plano (config webservice.fila).
 */
class AtualizarClientes implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(): void
    {
        AtualizadorDeClientes::atualizar();
    }
}
