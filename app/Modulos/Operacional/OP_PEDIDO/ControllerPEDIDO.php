<?php

namespace App\Modulos\Operacional\OP_PEDIDO;

use App\Modulos\Operacional\Enums;
use App\Sistema\Base\BaseController;
use App\Sistema\ModelProperty;

/**
 * Regras de negócio da tabela "Pedido" (OP_PEDIDO).
 *
 * Criado pelo dicionário de dados; o código escrito aqui nunca é sobrescrito.
 * Use de qualquer lugar com $this->getController('op', 'PEDIDO').
 *
 * Exemplo:
 *
 *   public function reajustar(int $id, float $percentual): void
 *   {
 *       $registro = new ModelProperty('op', 'PEDIDO', $id);
 *       $registro->setDecimal('preco', $registro->getDecimal('preco') * (1 + $percentual / 100));
 *       $registro->update();
 *   }
 */
class ControllerPEDIDO extends BaseController
{
}
