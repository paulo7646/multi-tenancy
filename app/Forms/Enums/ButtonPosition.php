<?php

namespace App\Forms\Enums;

use Filament\Support\Contracts\HasLabel;

enum ButtonPosition: string implements HasLabel
{
    case Record = 'record';
    case Header = 'header';
    case Bulk = 'bulk';

    public function getLabel(): string
    {
        return match ($this) {
            self::Record => 'Em cada linha',
            self::Header => 'No topo da listagem',
            self::Bulk => 'Em massa (registros selecionados)',
        };
    }

    /**
     * Assinatura do método gerado no controller.
     */
    public function methodSignature(string $modelClass): string
    {
        return match ($this) {
            self::Record => "{$modelClass} \$record",
            self::Header => '',
            self::Bulk => 'Collection $records',
        };
    }
}
