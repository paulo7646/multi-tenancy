<?php

namespace App\Webservice\Jobs;

use App\Webservice\Models\Operacao;
use App\Webservice\Motor\Integrador;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Executa uma operação em segundo plano (config webservice.fila). Dentro de um
 * cliente, o job roda no banco dele.
 */
class ExecutarOperacao implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  array<string, mixed>  $valores
     */
    public function __construct(public int $operacaoId, public array $valores = []) {}

    public function handle(): void
    {
        $operacao = Operacao::find($this->operacaoId);

        if ($operacao) {
            Integrador::executar($operacao, $this->valores);
        }
    }
}
