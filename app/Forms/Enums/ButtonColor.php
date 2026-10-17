<?php

namespace App\Forms\Enums;

use Filament\Support\Contracts\HasLabel;

enum ButtonColor: string implements HasLabel
{
    case Primary = 'primary';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';
    case Gray = 'gray';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => 'Principal (âmbar)',
            self::Success => 'Verde',
            self::Warning => 'Laranja',
            self::Danger => 'Vermelho',
            self::Info => 'Azul',
            self::Gray => 'Cinza',
        };
    }
}
