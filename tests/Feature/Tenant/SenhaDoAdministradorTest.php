<?php

use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use App\Jobs\CreateTenantUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;

/**
 * Regressão: a senha do administrador digitada no cadastro da empresa era gravada com hash
 * duplo (o CreateTenant aplicava Hash::make e o model Tenant aplica de novo), então o login
 * da empresa recusava a senha certa.
 */
function dadosDoCadastro(string $senha): array
{
    $pagina = new CreateTenant;

    return (fn () => $this->mutateFormDataBeforeCreate([
        'admin_email' => 'dono@empresa.test',
        'admin_password' => $senha,
        'domain' => 'empresa.test',
        'is_active' => true,
    ]))->call($pagina);
}

it('o cadastro passa a senha em texto, sem hash, para o model aplicar uma vez só', function () {
    $dados = dadosDoCadastro('Senha@123');

    expect($dados['password'])->toBe('Senha@123')
        ->and(Hash::isHashed($dados['password']))->toBeFalse()
        ->and($dados['email'])->toBe('dono@empresa.test')
        ->and($dados)->not->toHaveKeys(['admin_email', 'admin_password']);
});

it('a senha digitada confere com a gravada na empresa', function () {
    Event::fake([TenantCreated::class]); // sem criar o banco do cliente

    $tenant = Tenant::create([...dadosDoCadastro('Senha@123'), 'id' => 'senhateste']);

    expect(Hash::check('Senha@123', $tenant->fresh()->password))->toBeTrue()
        ->and(Hash::check('outra', $tenant->fresh()->password))->toBeFalse();
});

it('o usuário criado no banco da empresa recebe o mesmo hash, sem hashear de novo', function () {
    Event::fake([TenantCreated::class]);

    $tenant = Tenant::create([...dadosDoCadastro('Senha@123'), 'id' => 'senhateste2']);

    // é o que o CreateTenantUser entrega ao User (cast "hashed" mantém o que já é hash)
    $usuario = new User(['name' => 'admin', 'email' => $tenant->email, 'password' => $tenant->password]);

    expect($usuario->password)->toBe($tenant->password)
        ->and(Hash::check('Senha@123', $usuario->password))->toBeTrue();
});

it('redefinir a senha do administrador conserta uma empresa que ficou com hash duplo', function () {
    Event::fake([TenantCreated::class]);
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    // reproduz o erro antigo: a senha chegava ao model já com hash (e ele aplicava outro)
    $tenant = Tenant::create([
        'id' => 'senhareset', 'name' => 'admin', 'email' => 'adm@reset.test', 'is_active' => true,
        'password' => Hash::make('Senha@123'),
    ]);

    try {
        dispatch_sync(new CreateDatabase($tenant));
        Artisan::call('tenants:migrate', ['--tenants' => ['senhareset'], '--force' => true]);
        dispatch_sync(new CreateTenantUser($tenant));

        $entrar = fn (string $senha) => $tenant->run(fn () => Auth::guard('web')->attempt(['email' => 'adm@reset.test', 'password' => $senha]));

        expect($entrar('Senha@123'))->toBeFalse(); // o erro de antes: a senha certa não entrava

        $tenant->redefinirSenhaDoAdministrador('NovaSenha@456');

        expect($entrar('NovaSenha@456'))->toBeTrue()
            ->and($entrar('Senha@123'))->toBeFalse()
            ->and(Hash::check('NovaSenha@456', $tenant->fresh()->password))->toBeTrue();
    } finally {
        $tenant->delete();
    }
});

it('redefinir a senha avisa quando o administrador não existe no banco da empresa', function () {
    Event::fake([TenantCreated::class]);

    $tenant = Tenant::create(['id' => 'senhasemadmin', 'name' => 'admin', 'email' => 'nao@existe.test', 'is_active' => true, 'password' => 'x']);

    try {
        dispatch_sync(new CreateDatabase($tenant));
        Artisan::call('tenants:migrate', ['--tenants' => ['senhasemadmin'], '--force' => true]);

        expect(fn () => $tenant->redefinirSenhaDoAdministrador('NovaSenha@456'))
            ->toThrow(RuntimeException::class, 'não existe no banco desta empresa');
    } finally {
        $tenant->delete();
    }
});

/**
 * O run() do Stancl não tem try/finally: uma exceção dentro dele deixava a empresa inicializada
 * (conexão padrão = banco da empresa, até a sessão gravada lá) e quebrava o RefreshDatabase.
 */
it('executar() devolve a conexão ao estado anterior mesmo quando o callback lança exceção', function () {
    $tenant = new Tenant(['id' => 'executartest']);
    $antes = DB::getDefaultConnection();

    expect(fn () => $tenant->executar(fn () => throw new RuntimeException('falhou dentro da empresa')))
        ->toThrow(RuntimeException::class, 'falhou dentro da empresa');

    expect(tenancy()->initialized)->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe($antes);
});

it('executar() devolve o resultado do callback e também limpa o contexto', function () {
    $tenant = new Tenant(['id' => 'executartest2']);

    expect($tenant->executar(fn () => tenant('id')))->toBe('executartest2')
        ->and(tenancy()->initialized)->toBeFalse();
});
