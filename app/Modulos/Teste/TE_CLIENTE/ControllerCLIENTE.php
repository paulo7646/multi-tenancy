<?php

namespace App\Modulos\Teste\TE_CLIENTE;

use App\Sistema\Base\BaseController;
use App\Sistema\VQueryTabela;

/**
 * Regras de negócio da tabela "cliente" (TE_CLIENTE).
 *
 * Criado pelo dicionário de dados; o código escrito aqui nunca é sobrescrito.
 * Sobrescreva os ganchos de BaseController (beforeBeforePost, beforePost, beforeInsert, afterInsert,
 * afterAfterInsert, beforeDelete...): os formulários e o VQueryTabela os chamam ao gravar.
 * Use de qualquer lugar com $this->getController('te', 'CLIENTE').
 *
 * Exemplo:
 *
 *   public function reajustar(int $id, float $percentual): void
 *   {
 *       $registro = new VQueryTabela('te', 'CLIENTE', $id);
 *       $registro->setDecimal('preco', $registro->getDecimal('preco') * (1 + $percentual / 100));
 *       $registro->update();
 *   }
 */
class ControllerCLIENTE extends BaseController {}
