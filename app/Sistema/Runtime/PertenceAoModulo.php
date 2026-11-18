<?php

namespace App\Sistema\Runtime;

use App\Models\Md\Modulo;

/**
 * Tela feita à mão que pertence a um módulo (a classe declara $moduloPrefixo): dentro de uma empresa só abre
 * e só aparece no menu se a empresa tem o módulo. Fora de empresa (base pai) não muda nada.
 */
trait PertenceAoModulo
{
    public static function canAccess(): bool
    {
        return parent::canAccess() && static::moduloLiberado();
    }

    protected static function moduloLiberado(): bool
    {
        $modulo = Modulo::query()->where('prefixo', static::$moduloPrefixo)->first();

        // módulo ainda não cadastrado no dicionário (instalação nova): não trava
        return $modulo === null || Acesso::liberado($modulo);
    }
}
