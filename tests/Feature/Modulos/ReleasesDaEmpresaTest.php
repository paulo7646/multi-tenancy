<?php

use App\Models\User;
use App\Plataforma\Livewire\ReleasesDaEmpresa;
use App\Sistema\Releases\Publicador;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * Aba Releases do cliente: lista só as releases dos módulos da empresa e o botão Atualizar aplica as
 * pendentes no banco dela, sem tirar a empresa do contexto da requisição.
 */
beforeEach(fn () => compartilharConexaoCentral());

afterEach(function () {
    foreach (['rel1', 'rel2'] as $prefixo) {
        File::deleteDirectory(database_path("migrations/tenant/modulos/{$prefixo}"));
    }
});

it('só o super_admin da empresa vê e usa o botão Atualizar', function () {
    $empresa = empresaComBanco('relaba2');

    try {
        $modulo = moduloComMigration('rel1', 'rel1_item');
        Publicador::publicar($modulo, 'v1');
        $empresa->modulos()->attach($modulo->id);

        $empresa->fresh()->executar(function () {
            Filament::setCurrentPanel('client');
            test()->actingAs(User::factory()->make(['id' => 999]));

            Livewire::test(ReleasesDaEmpresa::class)
                ->assertSee('Pendente')
                ->assertActionHidden(TestAction::make('atualizar')->table());
        });
    } finally {
        $empresa->delete();
    }
});

it('mostra só as releases dos módulos da empresa e atualiza as pendentes', function () {
    $empresa = empresaComBanco('relaba1');

    try {
        $meu = moduloComMigration('rel1', 'rel1_item');
        $outro = moduloComMigration('rel2', 'rel2_item');
        Publicador::publicar($meu, 'notas do meu módulo');
        Publicador::publicar($outro, 'notas do outro módulo');
        $empresa->modulos()->attach($meu->id);

        $resultado = $empresa->fresh()->executar(function () {
            Filament::setCurrentPanel('client');
            test()->actingAs(User::first());

            $aba = Livewire::test(ReleasesDaEmpresa::class)
                ->assertSee('notas do meu módulo')
                ->assertDontSee('notas do outro módulo')
                ->assertSee('Pendente')
                ->callAction(TestAction::make('atualizar')->table());

            $aba->assertSee('Aplicada');

            return [
                'tabela' => Schema::hasTable('rel1_item'),
                'outra' => Schema::hasTable('rel2_item'),
                'contexto' => tenant()?->getTenantKey(),
            ];
        });

        expect($resultado['tabela'])->toBeTrue()
            ->and($resultado['outra'])->toBeFalse()
            ->and($resultado['contexto'])->toBe('relaba1'); // a empresa continua no contexto depois de atualizar
    } finally {
        $empresa->delete();
    }
});
