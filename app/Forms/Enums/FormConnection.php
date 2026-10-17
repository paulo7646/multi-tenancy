<?php

namespace App\Forms\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Onde a tabela do formulário é criada.
 */
enum FormConnection: string implements HasLabel, HasDescription, HasIcon, HasColor
{
    case Central = 'central';
    case Tenant = 'tenant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Central => 'Banco central',
            self::Tenant => 'Banco das empresas',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Central => 'Uma tabela só, no painel central (/admin do sistema).',
            self::Tenant => 'Uma tabela em cada empresa, no painel das empresas.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Central => 'heroicon-o-server',
            self::Tenant => 'heroicon-o-building-office-2',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Central => 'info',
            self::Tenant => 'success',
        };
    }

    /**
     * Pasta das migrations geradas.
     */
    public function migrationsPath(): string
    {
        return match ($this) {
            self::Central => database_path('migrations'),
            self::Tenant => database_path('migrations/tenant'),
        };
    }

    /**
     * Namespace e pasta dos resources do Filament gerados.
     */
    public function resourceNamespace(): string
    {
        return match ($this) {
            self::Central => 'App\\Filament\\Resources\\Forms',
            self::Tenant => 'App\\Filament\\Client\\Resources\\Forms',
        };
    }

    public function resourcePath(): string
    {
        return match ($this) {
            self::Central => app_path('Filament/Resources/Forms'),
            self::Tenant => app_path('Filament/Client/Resources/Forms'),
        };
    }
}
