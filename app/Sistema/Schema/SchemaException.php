<?php

namespace App\Forms\Schema;

use RuntimeException;

/**
 * A alteração de estrutura não pode ser aplicada. Nada foi alterado em nenhum banco.
 *
 * @property array<string, list<string>> $problems problemas por banco (central ou slug da empresa)
 */
class SchemaException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(collect($problems)
            ->map(fn (array $list, string $where) => "[{$where}]\n- " . implode("\n- ", $list))
            ->join("\n"));
    }
}
