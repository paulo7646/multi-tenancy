<?php

namespace App\Plataforma;

use App\Plataforma\Pages\ConfiguracoesDaEmpresa;

/**
 * Telas da plataforma (dados da empresa),
 * registradas no painel da base pai e no dos clientes.
 */
class Plataforma
{
    /**
     * @return list<class-string>
     */
    public static function recursos(): array
    {
        return [];
    }

    /**
     * @return list<class-string>
     */
    public static function paginas(): array
    {
        return [ConfiguracoesDaEmpresa::class];
    }
}
