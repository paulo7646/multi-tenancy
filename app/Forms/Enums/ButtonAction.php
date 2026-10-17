<?php

namespace App\Forms\Enums;

use Filament\Support\Contracts\HasLabel;

enum ButtonAction: string implements HasLabel
{
    case Controller = 'controller';
    case Url = 'url';

    public function getLabel(): string
    {
        return match ($this) {
            self::Controller => 'Executar método do controller',
            self::Url => 'Abrir endereço (URL)',
        };
    }
}
