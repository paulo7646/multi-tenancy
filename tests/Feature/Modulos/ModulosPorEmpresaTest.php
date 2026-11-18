<?php

use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\RelationManagers\ModulosRelationManager;
use App\Models\Md\Modulo;
use App\Models\User;
use App\Sistema\Enums\Banco;
use App\Sistema\Runtime\Acesso;
use App\Sistema\Runtime\ModuloNavegacao;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Acesso por módulo: cadastrar um módulo na empresa libera o acesso; sem cadastro o cliente nem
 * acessa nem vê o módulo no menu. O módulo "padrão" (Estrutura) é de todos os clientes.
 */
beforeEach(fn () => compartilharConexaoCentral());

function moduloDeTeste(string $prefixo, bool $padrao = false, Banco $banco = Banco::Tenant): Modulo
{
    return Modulo::create([
        'nome' => ucfirst($prefixo),
        'prefixo' => $prefixo,
        'pasta' => ucfirst($prefixo),
        'banco' => $banco,
        'padrao' => $padrao,
    ]);
}

it('o módulo padrão é liberado para qualquer empresa, os demais só depois de cadastrados nela', function () {
    $empresa = empresaComBanco('modemp1');

    try {
        $padrao = moduloDeTeste('est', padrao: true);
        $vendas = moduloDeTeste('ven');

        expect($padrao->liberadoPara($empresa))->toBeTrue()
            ->and($vendas->liberadoPara($empresa))->toBeFalse();

        $empresa->modulos()->attach($vendas->id);

        expect($vendas->liberadoPara($empresa->fresh()))->toBeTrue();

        $empresa->modulos()->detach($vendas->id);

        expect($vendas->liberadoPara($empresa->fresh()))->toBeFalse();
    } finally {
        $empresa->delete();
    }
});

it('uma empresa não enxerga os módulos cadastrados em outra', function () {
    $a = empresaComBanco('modemp2a');
    $b = empresaComBanco('modemp2b');

    try {
        $vendas = moduloDeTeste('ven');
        $a->modulos()->attach($vendas->id);

        expect($vendas->liberadoPara($a->fresh()))->toBeTrue()
            ->and($vendas->liberadoPara($b->fresh()))->toBeFalse();
    } finally {
        $a->delete();
        $b->delete();
    }
});

it('dentro da empresa o acesso só vale para módulo liberado, mesmo para o super_admin', function () {
    $empresa = empresaComBanco('modemp3');

    try {
        $padrao = moduloDeTeste('est', padrao: true);
        $vendas = moduloDeTeste('ven');
        $estoque = moduloDeTeste('esq');
        $empresa->modulos()->attach($vendas->id);

        $resultado = $empresa->executar(function () use ($padrao, $vendas, $estoque) {
            $this->actingAs(User::first()); // administrador da empresa (super_admin)

            return [Acesso::modulo($padrao), Acesso::modulo($vendas), Acesso::modulo($estoque)];
        });

        expect($resultado)->toBe([true, true, false]);
    } finally {
        $empresa->delete();
    }
});

it('o menu do cliente só mostra os módulos liberados', function () {
    $empresa = empresaComBanco('modemp4');

    try {
        moduloDeTeste('est', padrao: true);
        $vendas = moduloDeTeste('ven');
        moduloDeTeste('esq');
        $empresa->modulos()->attach($vendas->id);

        $grupos = $empresa->executar(function () {
            Filament::setCurrentPanel('client');
            $this->actingAs(User::first());

            return collect(ModuloNavegacao::para('client')[0])->map(fn ($grupo) => $grupo->getLabel())->all();
        });

        expect($grupos)->toBe(['Est', 'Ven']);
    } finally {
        $empresa->delete();
    }
});

it('a aba Módulos da empresa adiciona e remove módulos, oferecendo só os de cliente que não são padrão', function () {
    Filament::setCurrentPanel('admin');
    foreach (['view_any_tenant', 'view_tenant', 'update_tenant'] as $permissao) {
        Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']);
    }
    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $this->actingAs(tap(User::factory()->create())->assignRole($papel));

    $empresa = empresaComBanco('modemp5');

    try {
        $padrao = moduloDeTeste('est', padrao: true);
        $vendas = moduloDeTeste('ven');
        moduloDeTeste('sis', banco: Banco::Central);

        $aba = Livewire::test(ModulosRelationManager::class, ['ownerRecord' => $empresa, 'pageClass' => EditTenant::class])
            ->assertOk()
            ->assertSee('Sempre liberados para todas as empresas: Est') // aviso dos módulos padrão
            ->callAction(TestAction::make('attach')->table(), ['recordId' => $vendas->id])
            ->assertHasNoActionErrors();

        expect($empresa->fresh()->idsDosModulos())->toBe([$vendas->id]);

        $aba->callAction(TestAction::make('detach')->table($vendas));

        expect($empresa->fresh()->idsDosModulos())->toBe([])
            ->and($padrao->liberadoPara($empresa->fresh()))->toBeTrue();
    } finally {
        $empresa->delete();
    }
});

it('módulo da base pai não depende de empresa', function () {
    $central = moduloDeTeste('sis', banco: Banco::Central);

    expect($central->liberadoPara(null))->toBeTrue();
});
