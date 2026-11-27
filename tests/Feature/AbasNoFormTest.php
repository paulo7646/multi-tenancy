<?php

use App\Filament\Resources\TenantResource;

/**
 * Padrão de tela: um form só, com Tabs dentro dele. A primeira aba é "Geral" e os registros filhos
 * são abas do mesmo form (Abas::filha), nunca relation manager solto embaixo do formulário.
 */
it('nenhuma resource usa relation manager solto embaixo do form', function (string $resource) {
    expect($resource::getRelations())->toBe([]);
})->with([
    'empresa' => TenantResource::class,
]);
