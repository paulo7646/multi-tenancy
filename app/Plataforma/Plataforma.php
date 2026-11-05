<?php

namespace App\Plataforma;

use App\Plataforma\Pages\ConfiguracoesDaEmpresa;
use App\Plataforma\Resources\AssinaturaResource;
use App\Plataforma\Resources\DocumentoResource;
use App\Plataforma\Resources\ModeloImpressaoResource;
use App\Plataforma\Resources\PastaResource;

/**
 * Telas da plataforma (documentos, assinatura, impressão, dados da empresa),
 * registradas no painel da base pai e no dos clientes.
 */
class Plataforma
{
    /**
     * @return list<class-string>
     */
    public static function recursos(): array
    {
        return [DocumentoResource::class, PastaResource::class, AssinaturaResource::class, ModeloImpressaoResource::class];
    }

    /**
     * @return list<class-string>
     */
    public static function paginas(): array
    {
        return [ConfiguracoesDaEmpresa::class];
    }
}
