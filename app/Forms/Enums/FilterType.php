<?php

namespace App\Forms\Enums;

use Filament\Support\Contracts\HasLabel;

enum FilterType: string implements HasLabel
{
    case Select = 'select';
    case Ternary = 'ternary';
    case DateRange = 'date_range';
    case Text = 'text';

    public function getLabel(): string
    {
        return match ($this) {
            self::Select => 'Seleção (lista de valores)',
            self::Ternary => 'Sim / Não / Todos',
            self::DateRange => 'Período (de / até)',
            self::Text => 'Texto contém',
        };
    }
}
