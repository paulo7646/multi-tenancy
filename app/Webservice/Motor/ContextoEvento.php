<?php

namespace App\Webservice\Motor;

use App\Webservice\Models\Evento;
use App\Webservice\Models\Integracao;
use App\Webservice\Models\Objeto;

/**
 * O que a função do controller (evento do tipo código-fonte) recebe: o item do
 * retorno que está sendo processado e de onde ele veio.
 */
class ContextoEvento
{
    /**
     * @param  array<string, mixed>  $item  o item do objeto (atributos e filhos)
     * @param  array<string, mixed>  $dados  atributos do item e dos objetos pais (o item vence)
     */
    public function __construct(
        public readonly Integracao $integracao,
        public readonly Objeto $objeto,
        public readonly Evento $evento,
        public readonly array $item,
        public readonly array $dados,
        public readonly int $lote,
    ) {}

    public function valor(string $atributo, mixed $padrao = null): mixed
    {
        return $this->dados[$atributo] ?? $padrao;
    }
}
