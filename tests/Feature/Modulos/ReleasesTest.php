<?php

use App\Models\Md\MdRelease;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Releases\Aplicador;
use App\Sistema\Releases\Publicador;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Releases por módulo: o pai publica um pacote (dicionário + migrations do módulo) e cada empresa só
 * aplica os pacotes dos módulos que tem. Quem usa 2 módulos não baixa as atualizações do 3º.
 */
beforeEach(fn () => compartilharConexaoCentral());

afterEach(function () {
    foreach (['rel1', 'rel2'] as $prefixo) {
        File::deleteDirectory(database_path("migrations/tenant/modulos/{$prefixo}"));
    }
});

it('as migrations de módulo de cliente ficam numa pasta própria, que o tenants:migrate comum não percorre', function () {
    $modulo = new Modulo(['prefixo' => 'ven', 'banco' => Banco::Tenant]);

    expect(Banco::Tenant->migrationsPath($modulo))->toBe(database_path('migrations/tenant/modulos/ven'))
        ->and(Banco::Tenant->migrationsPath())->toBe(database_path('migrations/tenant'))
        ->and(Banco::Central->migrationsPath($modulo))->toBe(database_path('migrations'));
});

it('publica um pacote com o dicionário e as migrations do módulo, e não republica sem novidade', function () {
    $modulo = moduloComMigration('rel1', 'rel1_item');

    $release = Publicador::publicar($modulo, 'primeira versão');

    expect($release->versao)->toBe(1)
        ->and($release->notas)->toBe('primeira versão')
        ->and($release->pacote['modulo']['prefixo'])->toBe('rel1')
        ->and(collect($release->pacote['migrations'])->pluck('arquivo')->all())->toBe(['2026_11_18_000001_create_rel1_item_table.php']);

    expect(fn () => Publicador::publicar($modulo, 'igual'))->toThrow(RuntimeException::class, 'novidade');
});

it('a empresa só aplica as releases dos módulos que tem', function () {
    $com = empresaComBanco('relemp1');
    $sem = empresaComBanco('relemp2');

    try {
        $m1 = moduloComMigration('rel1', 'rel1_item');
        $m2 = moduloComMigration('rel2', 'rel2_item');
        Publicador::publicar($m1, 'v1');
        Publicador::publicar($m2, 'v1');

        $com->modulos()->attach($m1->id);
        $sem->modulos()->attach($m2->id);

        expect(Aplicador::pendentes($com->fresh())->pluck('modulo_id')->all())->toBe([$m1->id])
            ->and(Aplicador::pendentes($sem->fresh())->pluck('modulo_id')->all())->toBe([$m2->id]);

        Aplicador::aplicar($com->fresh());

        $temTabela = fn ($empresa, $tabela) => $empresa->executar(fn () => Schema::hasTable($tabela));

        expect($temTabela($com, 'rel1_item'))->toBeTrue()
            ->and($temTabela($com, 'rel2_item'))->toBeFalse()
            ->and($temTabela($sem, 'rel1_item'))->toBeFalse()
            ->and(Aplicador::pendentes($com->fresh()))->toHaveCount(0)
            ->and(Aplicador::pendentes($sem->fresh()))->toHaveCount(1);
    } finally {
        $com->delete();
        $sem->delete();
    }
});

it('só aplica o que foi publicado: migration nova no módulo espera a próxima release', function () {
    $empresa = empresaComBanco('relemp3');

    try {
        $modulo = moduloComMigration('rel1', 'rel1_item');
        Publicador::publicar($modulo, 'v1');
        $empresa->modulos()->attach($modulo->id);
        Aplicador::aplicar($empresa->fresh());

        // migration criada depois, ainda sem release
        File::put(Banco::Tenant->migrationsPath($modulo).'/2026_11_18_000002_create_rel1_extra_table.php', str_replace('rel1_item', 'rel1_extra', File::get(Banco::Tenant->migrationsPath($modulo).'/2026_11_18_000001_create_rel1_item_table.php')));

        Aplicador::aplicar($empresa->fresh());
        expect($empresa->executar(fn () => Schema::hasTable('rel1_extra')))->toBeFalse();

        Publicador::publicar($modulo, 'v2');
        expect(Aplicador::pendentes($empresa->fresh()))->toHaveCount(1);

        Aplicador::aplicar($empresa->fresh());
        expect($empresa->executar(fn () => Schema::hasTable('rel1_extra')))->toBeTrue()
            ->and(MdRelease::where('modulo_id', $modulo->id)->count())->toBe(2);
    } finally {
        $empresa->delete();
    }
});

it('release cumulativa: a v1 que mudou depois não trava a empresa, só a última pendente roda', function () {
    $empresa = empresaComBanco('relemp6');

    try {
        $modulo = moduloComMigration('rel1', 'rel1_item');
        $pasta = Banco::Tenant->migrationsPath($modulo);
        $arquivo = "{$pasta}/2026_11_18_000001_create_rel1_item_table.php";

        Publicador::publicar($modulo, 'v1');

        // o arquivo muda depois da v1 (ajuste de uma migration ainda não aplicada) e sai a v2
        File::put($arquivo, File::get($arquivo)."\n// ajuste\n");
        Publicador::publicar($modulo, 'v2');

        $empresa->modulos()->attach($modulo->id);

        expect(Aplicador::pendentes($empresa->fresh()))->toHaveCount(2)
            ->and(Aplicador::aplicar($empresa->fresh()))->toBe(2)
            ->and($empresa->executar(fn () => Schema::hasTable('rel1_item')))->toBeTrue()
            ->and(Aplicador::pendentes($empresa->fresh()))->toHaveCount(0)
            ->and($empresa->executar(fn () => DB::table('releases_aplicadas')->count()))->toBe(2); // v1 e v2 marcadas
    } finally {
        $empresa->delete();
    }
});

it('aplica primeiro o módulo padrão e só depois os outros', function () {
    $empresa = empresaComBanco('relemp7');

    try {
        // o módulo comum nasce antes (id menor) e o padrão depois: a ordem tem de vir da marca, não do id
        $comum = moduloComMigration('rel2', 'rel2_item');
        $padrao = moduloComMigration('rel1', 'rel1_item');
        $padrao->update(['padrao' => true]);

        Publicador::publicar($comum, 'v1');
        Publicador::publicar($padrao->fresh(), 'v1');
        $empresa->modulos()->attach($comum->id);

        Aplicador::aplicar($empresa->fresh());

        $ordem = $empresa->executar(fn () => DB::table('releases_aplicadas')->orderBy('id')->pluck('modulo')->all());

        expect($ordem)->toBe(['rel1', 'rel2']);
    } finally {
        $empresa->delete();
    }
});

it('recusa, no pacote, nome de migration que tenta sair da pasta do módulo', function () {
    $empresa = empresaComBanco('relemp5');

    try {
        $modulo = moduloComMigration('rel1', 'rel1_item');
        MdRelease::create([
            'modulo_id' => $modulo->id, 'versao' => 1, 'hash' => 'x', 'publicado_em' => now(),
            'pacote' => ['modulo' => [], 'tabelas' => [], 'migrations' => [['arquivo' => '../../../../app/Models/User.php', 'hash' => 'x']]],
        ]);
        $empresa->modulos()->attach($modulo->id);

        expect(fn () => Aplicador::aplicar($empresa->fresh()))->toThrow(RuntimeException::class, 'inválido');
    } finally {
        $empresa->delete();
    }
});

it('um módulo adicionado depois recebe o que já foi publicado', function () {
    $empresa = empresaComBanco('relemp4');

    try {
        $modulo = moduloComMigration('rel1', 'rel1_item');
        Publicador::publicar($modulo, 'v1');

        $empresa->modulos()->attach($modulo->id);
        Aplicador::aplicar($empresa->fresh());

        expect($empresa->executar(fn () => Schema::hasTable('rel1_item')))->toBeTrue();
    } finally {
        $empresa->delete();
    }
});
