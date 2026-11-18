<?php

use App\Enums\LicenseStatus;
use App\Filament\Resources\TenantResource\Pages\ViewCliente;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserLicense;
use App\Sistema\UsuariosDaEmpresa;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Página do cliente (usuário do banco da empresa): aba Geral com os dados dele. As licenças ficam no
 * banco pai, ligadas pelo e-mail, e são operadas na aba Licenças da empresa.
 */
beforeEach(function () {
    // a conexão "central" (user_licenses) é outra conexão: sem isso ela não enxerga a empresa criada
    // dentro da transação do teste e a FK de tenant_id falha
    compartilharConexaoCentral();

    Filament::setCurrentPanel('admin');

    foreach (['view_any_tenant', 'view_tenant', 'update_tenant', 'view_any_user::license', 'view_user::license', 'create_user::license', 'update_user::license', 'delete_user::license'] as $permissao) {
        Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
    }

    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $this->actingAs(tap(User::factory()->create())->assignRole($papel));
});

function clienteDaEmpresa(Tenant $empresa, string $nome, string $email): array
{
    return (new UsuariosDaEmpresa($empresa))->criar(['name' => $nome, 'email' => $email, 'password' => 'Segredo@123']);
}

function paginaDoCliente(Tenant $empresa, array $cliente)
{
    return Livewire::test(ViewCliente::class, ['record' => $empresa->getKey(), 'cliente' => $cliente['id']]);
}

it('mostra as informações gerais do cliente na aba Geral', function () {
    $empresa = empresaComBanco('clienteemp1');

    try {
        $cliente = clienteDaEmpresa($empresa, 'Maria Souza', 'maria@clienteemp1.test');

        paginaDoCliente($empresa, $cliente)->assertOk()
            ->assertSee('Geral')
            ->assertSee('Maria Souza')
            ->assertSee('maria@clienteemp1.test');
    } finally {
        $empresa->delete();
    }
});

it('cliente que não existe na empresa dá 404', function () {
    $empresa = empresaComBanco('clienteemp2');

    try {
        Livewire::test(ViewCliente::class, ['record' => $empresa->getKey(), 'cliente' => 99999])->assertNotFound();
    } finally {
        $empresa->delete();
    }
});

it('trocar o e-mail do cliente leva as licenças da empresa junto, sem tocar nas de outros', function () {
    $empresa = empresaComBanco('clienteemp6');

    try {
        $maria = clienteDaEmpresa($empresa, 'Maria', 'maria@clienteemp6.test');
        $propria = UserLicense::create(['user_email' => 'maria@clienteemp6.test', 'tenant_id' => $empresa->getKey(), 'license_key' => (string) Str::uuid(), 'status' => LicenseStatus::Inactive]);
        $global = UserLicense::create(['user_email' => 'maria@clienteemp6.test', 'tenant_id' => null, 'license_key' => (string) Str::uuid(), 'status' => LicenseStatus::Inactive]);

        (new UsuariosDaEmpresa($empresa))->atualizar($maria['id'], ['name' => 'Maria', 'email' => 'maria.nova@clienteemp6.test']);

        expect($propria->fresh()->user_email)->toBe('maria.nova@clienteemp6.test')
            ->and($global->fresh()->user_email)->toBe('maria@clienteemp6.test'); // global não pertence à empresa
    } finally {
        UserLicense::query()->where('user_email', 'like', '%@clienteemp6.test')->delete();
        $empresa->delete();
    }
});

it('sem permissão para ver a empresa, a página do cliente nega o acesso', function () {
    $empresa = empresaComBanco('clienteemp7');

    try {
        $maria = clienteDaEmpresa($empresa, 'Maria', 'maria@clienteemp7.test');
        $this->actingAs(User::factory()->create());

        paginaDoCliente($empresa, $maria)->assertForbidden();
    } finally {
        $empresa->delete();
    }
});
