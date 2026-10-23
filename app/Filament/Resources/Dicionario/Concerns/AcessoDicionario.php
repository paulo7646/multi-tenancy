<?php

namespace App\Filament\Resources\Dicionario\Concerns;

use App\Sistema\Permissoes;

/**
 * O dicionário gera código (que vai para o git) e roda migrations: só em
 * ambiente local, para super_admin. Em produção as telas nem aparecem; lá o
 * cadastro chega pelo git (php artisan md:importar).
 */
trait AcessoDicionario
{
    public static function canAccess(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) auth()->user()?->hasRole(Permissoes::superAdmin());
    }

    public static function getNavigationGroup(): string
    {
        return 'Dicionário de dados';
    }
}
