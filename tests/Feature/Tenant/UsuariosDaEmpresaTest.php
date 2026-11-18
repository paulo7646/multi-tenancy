<?php

use App\Models\User;
use App\Sistema\UsuariosDaEmpresa;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * O banco pai não enxerga as linhas do cliente: o serviço roda tudo dentro do banco da empresa
 * (Tenant::executar) e devolve só arrays, sem deixar a conexão apontando para o cliente.
 * O helper empresaComBanco() está em tests/Pest.php.
 */
it('lista, cria, consulta, edita e exclui usuários no banco da empresa', function () {
    $tenant = empresaComBanco('usuariosemp1');

    try {
        $servico = new UsuariosDaEmpresa($tenant);

        $criado = $servico->criar(['name' => 'Maria', 'email' => 'maria@usuariosemp1.test', 'password' => 'Segredo@123']);

        expect($criado)->toHaveKeys(['id', 'name', 'email'])->not->toHaveKey('password')
            ->and($servico->consultar($criado['id'])['email'])->toBe('maria@usuariosemp1.test')
            ->and(collect($servico->listar()['dados'])->pluck('email'))->toContain('maria@usuariosemp1.test', $tenant->email);

        // a senha foi gravada com hash, uma vez só
        $hash = $tenant->executar(fn () => User::withoutGlobalScopes()->find($criado['id'])->password);
        expect(Hash::check('Segredo@123', $hash))->toBeTrue();

        $editado = $servico->atualizar($criado['id'], ['name' => 'Maria Souza', 'email' => 'maria@usuariosemp1.test']);
        expect($editado['name'])->toBe('Maria Souza');

        // senha em branco na edição mantém a atual
        $hashDepois = $tenant->executar(fn () => User::withoutGlobalScopes()->find($criado['id'])->password);
        expect($hashDepois)->toBe($hash);

        $servico->excluir($criado['id']);
        expect(fn () => $servico->consultar($criado['id']))->toThrow(RuntimeException::class);

        // nada ficou inicializado no banco do cliente
        expect(tenancy()->initialized)->toBeFalse();
    } finally {
        $tenant->delete();
    }
});

it('valida e-mail repetido e senha curta na criação', function () {
    $tenant = empresaComBanco('usuariosemp2');

    try {
        $servico = new UsuariosDaEmpresa($tenant);

        expect(fn () => $servico->criar(['name' => 'X', 'email' => $tenant->email, 'password' => 'Segredo@123']))
            ->toThrow(ValidationException::class)
            ->and(fn () => $servico->criar(['name' => 'X', 'email' => 'x@usuariosemp2.test', 'password' => '123']))
            ->toThrow(ValidationException::class);
    } finally {
        $tenant->delete();
    }
});

it('protege o administrador da empresa: não exclui nem troca o e-mail, e a senha fica igual à da empresa', function () {
    $tenant = empresaComBanco('usuariosemp3');

    try {
        $servico = new UsuariosDaEmpresa($tenant);
        $admin = collect($servico->listar()['dados'])->firstWhere('email', $tenant->email);

        expect(fn () => $servico->excluir($admin['id']))->toThrow(RuntimeException::class, 'administrador')
            ->and(fn () => $servico->atualizar($admin['id'], ['name' => 'Admin', 'email' => 'outro@usuariosemp3.test']))
            ->toThrow(RuntimeException::class, 'administrador');

        $servico->atualizar($admin['id'], ['name' => 'Admin 2', 'email' => $tenant->email, 'password' => 'NovaSenha@456']);

        expect(Hash::check('NovaSenha@456', $tenant->fresh()->password))->toBeTrue();
    } finally {
        $tenant->delete();
    }
});

it('busca por nome ou e-mail e pagina', function () {
    $tenant = empresaComBanco('usuariosemp4');

    try {
        $servico = new UsuariosDaEmpresa($tenant);
        foreach (['Ana', 'Bruno', 'Carla'] as $nome) {
            $servico->criar(['name' => $nome, 'email' => strtolower($nome).'@usuariosemp4.test', 'password' => 'Segredo@123']);
        }

        expect(collect($servico->listar(busca: 'bruno')['dados'])->pluck('name')->all())->toBe(['Bruno'])
            ->and($servico->listar(porPagina: 2)['dados'])->toHaveCount(2)
            ->and($servico->listar(porPagina: 2)['total'])->toBe(4);
    } finally {
        $tenant->delete();
    }
});
