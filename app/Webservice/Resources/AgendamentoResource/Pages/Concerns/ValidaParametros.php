<?php

namespace App\Webservice\Resources\AgendamentoResource\Pages\Concerns;

use App\Webservice\Models\Operacao;
use Illuminate\Validation\ValidationException;

/**
 * Layout de EDI agendado não tem quem digite: os parâmetros obrigatórios sem
 * valor fixo na operação precisam de valor no agendamento.
 */
trait ValidaParametros
{
    protected function validarParametrosObrigatorios(): void
    {
        if (($this->data['tipo'] ?? null) !== 'layout_edi' || blank($this->data['operacao_id'] ?? null)) {
            return;
        }

        $informados = $this->data['parametros'] ?? [];

        $faltam = Operacao::with('parametros')->find($this->data['operacao_id'])?->parametros
            ->filter(fn ($p) => $p->obrigatorio && blank($p->valor) && blank($informados[$p->nome] ?? null))
            ->pluck('nome');

        if ($faltam?->isNotEmpty()) {
            throw ValidationException::withMessages([
                'data.parametros' => 'Informe o valor dos parâmetros obrigatórios: ' . $faltam->join(', ') . '.',
            ]);
        }
    }
}
