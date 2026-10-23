<?php

namespace App\Sistema\Runtime;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;

/**
 * Tabelas de ligação (N:N): ex. PRODUTO_PEDIDO, com uma FK para PEDIDO e outra
 * para PRODUTO. Numa tab page do Pedido, a ligação é a FK que aponta para o
 * Pedido; o "outro lado" é a FK que aponta para o Produto.
 */
class Ligacao
{
    /**
     * Primeira FK de $tabela que aponta para $alvo (para sugerir a ligação da tab page).
     */
    public static function fkPara(?MdTabela $tabela, ?MdTabela $alvo): ?MdCampo
    {
        if (! $tabela || ! $alvo) {
            return null;
        }

        $tabela->loadMissing('campos');

        return $tabela->campos->first(fn (MdCampo $c) => $c->isFk() && (int) $c->fk_tabela_id === (int) $alvo->id);
    }

    /**
     * O outro lado da tabela de ligação: a FK que não é a da ligação com a
     * origem, e a tabela para onde ela aponta.
     *
     * @param  string|null  $campoLigacao  uid da FK que liga à origem (se já escolhida)
     * @return object{campo: MdCampo, tabela: MdTabela}|null
     */
    public static function outroLado(MdTabela $ligacao, ?MdTabela $origem, ?string $campoLigacao = null): ?object
    {
        $ligacao->loadMissing('campos.fkTabela');

        $daOrigem = $campoLigacao
            ? $ligacao->campos->firstWhere('uid', $campoLigacao)
            : static::fkPara($ligacao, $origem);

        $campo = $ligacao->campos->first(fn (MdCampo $c) => $c->isFk() && $c->fkTabela && $c->uid !== $daOrigem?->uid);

        return $campo ? (object) ['campo' => $campo, 'tabela' => $campo->fkTabela] : null;
    }
}
