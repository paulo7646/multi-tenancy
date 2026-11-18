<?php

use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\RelationManagers\UsuariosRelationManager;
use App\Models\Tenant;
use App\Models\User;
use App\Sistema\UsuariosDaEmpresa;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Aba "Usuários" na página da empresa: o datagrid e as ações passam pelo serviço, que roda no
 * banco do cliente (o pai não enxerga essas linhas).
 */
beforeEach(function () {
    Filament::setCurrentPanel('admin');
    // o super_admin do Shield é um papel comum com todas as permissões (define_via_gate = false)
    foreach (['view_any_tenant', 'view_tenant', 'update_tenant', 'view_any_user::license'] as $permissao) {
        Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
    }

    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $this->actingAs(tap(User::factory()->create())->assignRole($papel));
});

function abaUsuarios(Tenant $empresa)
{
    return Livewire::test(UsuariosRelationManager::class, ['ownerRecord' => $empresa, 'pageClass' => EditTenant::class]);
}

it('mostra os usuários do banco da empresa no datagrid', function () {
    $empresa = empresaComBanco('abausuarios1');

    try {
        (new UsuariosDaEmpresa($empresa))->criar(['name' => 'Maria Souza', 'email' => 'maria@abausuarios1.test', 'password' => 'Segredo@123']);

        abaUsuarios($empresa)->assertOk()
            ->assertSee('Maria Souza')
            ->assertSee('maria@abausuarios1.test')
            ->assertSee($empresa->email);
    } finally {
        $empresa->delete();
    }
});

it('cria, edita e exclui usuário pela aba', function () {
    $empresa = empresaComBanco('abausuarios2');

    try {
        $servico = new UsuariosDaEmpresa($empresa);

        abaUsuarios($empresa)
            ->callAction(TestAction::make('criar')->table(), ['name' => 'Carlos', 'email' => 'carlos@abausuarios2.test', 'password' => 'Segredo@123'])
            ->assertHasNoActionErrors();

        $carlos = collect($servico->listar()['dados'])->firstWhere('email', 'carlos@abausuarios2.test');
        expect($carlos)->not->toBeNull();

        abaUsuarios($empresa)
            ->callAction(TestAction::make('editar')->table($carlos['id']), ['name' => 'Carlos Lima', 'email' => 'carlos@abausuarios2.test', 'password' => ''])
            ->assertHasNoActionErrors();

        expect($servico->consultar($carlos['id'])['name'])->toBe('Carlos Lima');

        abaUsuarios($empresa)->callAction(TestAction::make('excluir')->table($carlos['id']));

        expect(fn () => $servico->consultar($carlos['id']))->toThrow(RuntimeException::class)
            ->and(tenancy()->initialized)->toBeFalse();
    } finally {
        $empresa->delete();
    }
});

it('não deixa excluir o administrador da empresa pela aba', function () {
    $empresa = empresaComBanco('abausuarios3');

    try {
        $admin = collect((new UsuariosDaEmpresa($empresa))->listar()['dados'])->firstWhere('email', $empresa->email);

        abaUsuarios($empresa)->assertActionHidden(TestAction::make('excluir')->table($admin['id']));
    } finally {
        $empresa->delete();
    }
});

it('as abas ficam dentro do form da empresa, sem relation manager solto embaixo', function () {
    expect(TenantResource::getRelations())->toBe([]);
});

it('a página de edição renderiza as três abas, sem relações soltas embaixo do formulário', function () {
    $empresa = empresaComBanco('abausuarios5');

    try {
        Livewire::test(EditTenant::class, ['record' => $empresa->getKey()])
            ->assertOk()
            ->assertSeeInOrder(['Geral', 'Clientes', 'Licenças']);
    } finally {
        $empresa->delete();
    }
});

it('sem permissão de editar a empresa, a aba só consulta', function () {
    $empresa = empresaComBanco('abausuarios4');

    try {
        $leitor = Role::firstOrCreate(['name' => 'leitor_empresas', 'guard_name' => 'web']);
        $leitor->syncPermissions(['view_tenant']);
        $this->actingAs(tap(User::factory()->create())->assignRole($leitor));

        abaUsuarios($empresa)->assertOk()
            ->assertSee($empresa->email)
            ->assertActionHidden(TestAction::make('criar')->table())
            ->assertActionHidden(TestAction::make('editar')->table(1));

        // sem nem visualizar a empresa, a aba nega o acesso
        $this->actingAs(User::factory()->create());
        abaUsuarios($empresa)->assertForbidden();
    } finally {
        $empresa->delete();
    }
});
