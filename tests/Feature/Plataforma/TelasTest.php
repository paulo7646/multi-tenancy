<?php

use App\Models\User;
use App\Plataforma\Pages\ConfiguracoesDaEmpresa;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $usuario = User::factory()->create();
    // o super_admin do Shield recebe todas as permissões (as das migrations da plataforma incluídas)
    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $usuario->assignRole($papel);
    $this->actingAs($usuario);
});

it('as telas da plataforma carregam', function (string $pagina) {
    Livewire::test($pagina)->assertOk();
})->with([
    'dados da empresa' => ConfiguracoesDaEmpresa::class,
]);
