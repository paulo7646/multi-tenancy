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

        return (bool) $user
            && static::liberado($modulo)
            && ($user->hasRole(Permissoes::superAdmin()) || $user->can($modulo->permissao()));
    }

    /**
     * O módulo está liberado para a empresa atual? Dentro de uma empresa só valem os módulos padrão
     * (ex.: Estrutura) e os cadastrados nela, até para o super_admin. Fora de empresa (base pai) vale tudo.
     */
    public static function liberado(Modulo $modulo): bool
    {
        $empresa = tenancy()->initialized ? tenant() : null;

        return $empresa === null || $modulo->liberadoPara($empresa);
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
