<?php

namespace App\Webservice\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Telas aninhadas do webservice (operação, versão, objeto, evento): não aparecem
 * no menu, abrem pelos relation managers do registro pai.
 */
abstract class ResourceFilho extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    /** Sem tela de lista própria: o "início" do caminho é a lista de webservices */
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return WebserviceResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant);
    }

    /** Endereço da tela do registro pai (para o botão Voltar) */
    abstract public static function urlPai($record): string;
}
