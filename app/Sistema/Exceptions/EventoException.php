<?php

namespace App\Sistema\Exceptions;

use RuntimeException;

/**
 * Interrompe a operação (incluir, alterar, excluir, botão) com uma mensagem
 * para o usuário. Lançada por $this->abortar('...') nas classes do sistema;
 * a transação é desfeita e a tela mostra a mensagem.
 */
class EventoException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $titulo = null)
    {
        parent::__construct($message);
    }
}
