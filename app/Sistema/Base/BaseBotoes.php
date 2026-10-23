<?php

namespace App\Sistema\Base;

use App\Models\Md\MdTabela;
use App\Sistema\Base\Concerns\Utilidades;
use Livewire\Component;

/**
 * Base das classes de botões (BotoesProduto.php: nome_arquivo dos botões): um método por botão
 * (nome físico), dos formulários e dos gerenciamentos.
 *
 * Retorno dos métodos:
 *  - string: URL para redirecionar;
 *  - false: não mostra nada (você já notificou com $this->notificar());
 *  - qualquer outro: mostra "{título do botão}: concluído".
 *
 * Os métodos rodam dentro de uma transação: $this->abortar('mensagem')
 * desfaz tudo e mostra a mensagem.
 */
abstract class BaseBotoes
{
    use Utilidades;

    /** Tela (Livewire) que disparou o botão */
    public ?Component $tela = null;

    public function __construct(public readonly MdTabela $tabela) {}
}
