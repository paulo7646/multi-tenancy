<?php

use App\Filament\Resources\Dicionario\ReleaseResource;
use App\Filament\Resources\Dicionario\ReleaseResource\Pages\ManageReleases;
use App\Models\Md\MdRelease;
use App\Models\Md\Modulo;
use App\Models\User;
use App\Sistema\Enums\Banco;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Gerador de pacotes (base pai): publica a release de um módulo; sem novidade, avisa e não publica.
 */
beforeEach(function () {
    compartilharConexaoCentral();

    Filament::setCurrentPanel('admin');
    $this->actingAs(tap(User::factory()->create())->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])));
});

afterEach(fn () => File::deleteDirectory(database_path('migrations/tenant/modulos/rel1')));

it('publica o pacote do módulo pela tela e lista a release', function () {
    $modulo = moduloComMigration('rel1', 'rel1_item');

    Livewire::test(ManageReleases::class)
        ->callAction(TestAction::make('create'), ['modulo_id' => $modulo->id, 'notas' => 'primeira'])
        ->assertHasNoActionErrors();

    $release = MdRelease::where('modulo_id', $modulo->id)->first();

    expect($release)->not->toBeNull()
        ->and($release->versao)->toBe(1)
        ->and($release->notas)->toBe('primeira');

    Livewire::test(ManageReleases::class)->assertCanSeeTableRecords([$release]);
});

it('sem novidade desde a última release, não publica de novo', function () {
    $modulo = moduloComMigration('rel1', 'rel1_item');

    $tela = Livewire::test(ManageReleases::class)
        ->callAction(TestAction::make('create'), ['modulo_id' => $modulo->id]);

    $tela->callAction(TestAction::make('create'), ['modulo_id' => $modulo->id]);

    expect(MdRelease::where('modulo_id', $modulo->id)->count())->toBe(1);
});

it('módulo da base pai não aparece para publicar e a tela é só do super_admin', function () {
    Modulo::create(['nome' => 'Sistema', 'prefixo' => 'sis', 'pasta' => 'Sistema', 'banco' => Banco::Central]);

    $this->actingAs(User::factory()->create());

    expect(ReleaseResource::canAccess())->toBeFalse();
});
