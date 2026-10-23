<?php

namespace App\Sistema\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Onde a tabela é criada: na base pai (banco central) ou em cada cliente (tenant).
 */
enum Banco: string implements HasLabel, HasDescription, HasIcon, HasColor
{
    case Central = 'central';
    case Tenant = 'tenant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Central => 'Base pai',
            self::Tenant => 'Clientes',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Central => 'Uma tabela só, no banco central (painel do sistema).',
            self::Tenant => 'Uma tabela em cada cliente (painel das empresas).',
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
     * Painel do Filament onde as telas da tabela aparecem.
     */
    public function painel(): string
    {
        return match ($this) {
            self::Central => 'admin',
            self::Tenant => 'client',
        };
    }

    public static function doPainel(string $painel): ?self
    {
        return match ($painel) {
            'admin' => self::Central,
            'client' => self::Tenant,
            default => null,
        };
    }

    public function migrationsPath(): string
    {
        return match ($this) {
            self::Central => database_path('migrations'),
            self::Tenant => database_path('migrations/tenant'),
        };
    }

    public function comandoMigrate(): string
    {
        return match ($this) {
            self::Central => 'migrate',
            self::Tenant => 'tenants:migrate',
        };
    }
}
