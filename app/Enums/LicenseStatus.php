<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Fluxo da licença de usuário:
 * Cadastrada (solicitada pela empresa) -> Em análise -> Ativa / Desativada
 */
enum LicenseStatus: string implements HasLabel, HasColor, HasIcon
{
    case Requested = 'requested';
    case InReview = 'in_review';
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Cadastrada',
            self::InReview => 'Em análise',
            self::Active => 'Ativa',
            self::Inactive => 'Desativada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'info',
            self::InReview => 'warning',
            self::Active => 'success',
            self::Inactive => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Requested => 'heroicon-m-inbox-arrow-down',
            self::InReview => 'heroicon-m-magnifying-glass',
            self::Active => 'heroicon-m-check-circle',
            self::Inactive => 'heroicon-m-x-circle',
        };
    }
}
