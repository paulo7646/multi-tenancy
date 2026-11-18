<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Sistema\Releases\Aplicador;
use Throwable;

/**
 * Empresa nova (ou que ganhou um módulo): aplica as releases já publicadas dos módulos dela, para ela
 * já nascer com as tabelas deles. As releases futuras chegam pela aba Releases das configurações.
 */
class AplicarReleasesDaEmpresa
{
    public function __construct(protected Tenant $tenant) {}

    public function handle(): void
    {
        // falhar aqui não pode deixar a empresa pela metade (sem a pasta Vue, por exemplo): ela já existe e o
        // que faltar chega pela aba Releases dela, com o detalhe no log
        try {
            Aplicador::aplicar($this->tenant);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
