<?php

use App\Filament\Resources\TenantResource;
use App\Webservice\Resources\EventoResource;
use App\Webservice\Resources\IntegracaoResource;
use App\Webservice\Resources\ObjetoResource;
use App\Webservice\Resources\OperacaoResource;
use App\Webservice\Resources\VersaoResource;
use App\Webservice\Resources\WebserviceResource;

/**
 * Padrão de tela: um form só, com Tabs dentro dele. A primeira aba é "Geral" e os registros filhos
 * são abas do mesmo form (Abas::filha), nunca relation manager solto embaixo do formulário.
 */
it('nenhuma resource usa relation manager solto embaixo do form', function (string $resource) {
    expect($resource::getRelations())->toBe([]);
})->with([
    'empresa' => TenantResource::class,
    'webservice' => WebserviceResource::class,
    'operação' => OperacaoResource::class,
    'versão' => VersaoResource::class,
    'objeto' => ObjetoResource::class,
    'evento' => EventoResource::class,
    'integração' => IntegracaoResource::class,
]);
