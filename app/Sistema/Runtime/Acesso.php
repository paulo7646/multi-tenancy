<?php

namespace App\Sistema\Runtime;

use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Permissoes;
use Filament\Facades\Filament;

/**
 * Quem acessa os módulos: pelos papéis (permissão modulo_{prefixo}), no painel
 * certo (base pai no central, clientes nas empresas). As telas (Resources)
 * usam também as permissões do Shield.
 */
class Acesso
{
    public static function modulo(Modulo $modulo): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->hasRole(Permissoes::superAdmin()) || $user->can($modulo->permissao()));
    }

    /**
     * Módulo acessível e do banco do painel (base pai no central, clientes nas empresas).
     */
    public static function moduloNoPainel(Modulo $modulo, ?string $painel = null): bool
    {
        $painel ??= Filament::getCurrentPanel()?->getId();

        return $modulo->banco === Banco::doPainel((string) $painel) && static::modulo($modulo);
    }
}
