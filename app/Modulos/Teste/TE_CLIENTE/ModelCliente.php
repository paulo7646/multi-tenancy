<?php

namespace App\Modulos\Teste\TE_CLIENTE;

use App\Sistema\Base\BaseModel;

/**
 * Tabela "cliente" (TE_CLIENTE) do módulo teste.
 *
 * Criado pelo dicionário de dados. As regras da tabela (beforePost, afterPost,
 * afterAfterPost, beforeDelete...) ficam no controller dela; o model guarda os
 * dados e carrega o registro (set/persiste). Só o bloco <estrutura> é recriado.
 */
class ModelCliente extends BaseModel
{
    // <estrutura> gerado pelo dicionário de dados: não edite este bloco
    protected static string $mdTabela = 'TE_CLIENTE';

    protected $table = 'TE_CLIENTE';

    protected $fillable = [
        'NOME',
    ];

    protected $hidden = [];

    protected function casts(): array
    {
        return [

        ];
    }

    // </estrutura>
}
