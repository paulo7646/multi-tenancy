<?php

namespace App\Modulos\Operacional\OP_PRODUTO;

use App\Modulos\Operacional\Enums;
use App\Sistema\Base\BaseController;
use App\Sistema\ModelProperty;

/**
 * Regras de negócio da tabela "produto" (OP_PRODUTO).
 *
 * Criado pelo dicionário de dados; o código escrito aqui nunca é sobrescrito.
 * Use de qualquer lugar com $this->getController('op', 'PRODUTO').
 *
 * Exemplo:
 *
 *   public function reajustar(int $id, float $percentual): void
 *   {
 *       $registro = new ModelProperty('op', 'PRODUTO', $id);
 *       $registro->setDecimal('preco', $registro->getDecimal('preco') * (1 + $percentual / 100));
 *       $registro->update();
 *   }
 */
class ControllerPRODUTO extends BaseController
{
}
