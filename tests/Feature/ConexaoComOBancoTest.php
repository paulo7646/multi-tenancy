<?php

/**
 * Conexão persistente: a base pai pode reaproveitar a conexão entre as requisições, mas a
 * dos clientes tem flag própria (persistente multiplica: processos do PHP x clientes, e
 * impede apagar o banco de um cliente enquanto a sessão estiver aberta).
 */
function lerConfigDoBanco(array $env): array
{
    foreach ($env as $chave => $valor) {
        putenv("{$chave}={$valor}");
    }

    try {
        return require config_path('database.php');
    } finally {
        foreach (array_keys($env) as $chave) {
            putenv($chave);
        }
    }
}

it('o molde da conexão dos clientes é a tenant_template', function () {
    expect(config('tenancy.database.template_tenant_connection'))->toBe('tenant_template')
        ->and(config('database.connections.tenant_template.driver'))->toBe('pgsql');
});

it('por padrão nenhuma conexão é persistente', function () {
    $conexoes = lerConfigDoBanco([])['connections'];

    foreach (['central', 'pgsql', 'tenant_template'] as $nome) {
        expect($conexoes[$nome]['options'])->not->toHaveKey(PDO::ATTR_PERSISTENT);
    }
});

it('DB_PERSISTENT liga a base pai e deixa a dos clientes desligada', function () {
    $conexoes = lerConfigDoBanco(['DB_PERSISTENT' => 'true'])['connections'];

    expect($conexoes['central']['options'][PDO::ATTR_PERSISTENT])->toBeTrue()
        ->and($conexoes['pgsql']['options'][PDO::ATTR_PERSISTENT])->toBeTrue()
        ->and($conexoes['tenant_template']['options'])->not->toHaveKey(PDO::ATTR_PERSISTENT);
});

it('DB_TENANT_PERSISTENT liga só a conexão dos clientes', function () {
    $conexoes = lerConfigDoBanco(['DB_TENANT_PERSISTENT' => 'true'])['connections'];

    expect($conexoes['tenant_template']['options'][PDO::ATTR_PERSISTENT])->toBeTrue()
        ->and($conexoes['central']['options'])->not->toHaveKey(PDO::ATTR_PERSISTENT);
});

it('DB_EMULATE_PREPARES vale para todas (PgBouncer em modo transaction antigo)', function () {
    $conexoes = lerConfigDoBanco(['DB_EMULATE_PREPARES' => 'true'])['connections'];

    foreach (['central', 'pgsql', 'tenant_template'] as $nome) {
        expect($conexoes[$nome]['options'][PDO::ATTR_EMULATE_PREPARES])->toBeTrue();
    }
});

it('DB_SSLMODE troca o sslmode das três conexões', function () {
    $conexoes = lerConfigDoBanco(['DB_SSLMODE' => 'disable'])['connections'];

    foreach (['central', 'pgsql', 'tenant_template'] as $nome) {
        expect($conexoes[$nome]['sslmode'])->toBe('disable');
    }
});

it('a chave database.persistente acompanha as flags (é ela que o app lê, não o env)', function () {
    expect(lerConfigDoBanco([])['persistente'])->toBeFalse()
        ->and(lerConfigDoBanco(['DB_PERSISTENT' => 'true'])['persistente'])->toBeTrue()
        ->and(lerConfigDoBanco(['DB_TENANT_PERSISTENT' => 'true'])['persistente'])->toBeTrue();
});

it('o molde dos clientes não tem url (senão todos cairiam no banco da DB_URL)', function () {
    expect(lerConfigDoBanco(['DB_URL' => 'pgsql://u:p@h/outro'])['connections']['tenant_template'])->not->toHaveKey('url');
});
