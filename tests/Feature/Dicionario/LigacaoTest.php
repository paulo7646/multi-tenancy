<?php

use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\CreateMdTabela;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\EditMdTabela;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Models\User;
use App\Sistema\Enums\TipoTabela;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('só a ligação com exibe filho fica sem formulário e gerenciamento', function (TipoTabela $tipo, bool $exibeFilho, bool $temTelas, bool $mostraFilho) {
    $tabela = new MdTabela(['tipo' => $tipo, 'exibe_filho' => $exibeFilho]);

    expect($tabela->temTelas())->toBe($temTelas)
        ->and($tabela->exibeFilho())->toBe($mostraFilho);
})->with([
    'ligação exibindo o filho' => [TipoTabela::Ligacao, true, false, true],
    'ligação com telas próprias' => [TipoTabela::Ligacao, false, true, false],
    'cadastro' => [TipoTabela::Cadastro, true, true, false],
    'lista' => [TipoTabela::Lista, true, true, false],
]);

it('a ligação nasce exibindo o filho', function () {
    expect((new MdTabela(['tipo' => TipoTabela::Ligacao]))->exibeFilho())->toBeTrue();
});

/**
 * Cadastra uma tabela de ligação pela tela e devolve a tabela criada.
 */
function cadastrarLigacao(bool $exibeFilho): MdTabela
{
    $modulo = Modulo::create(['nome' => 'Lig', 'prefixo' => 'l'.Str::lower(Str::random(3)), 'pasta' => 'Lig'.Str::random(5), 'banco' => 'central', 'roles' => []]);

    Livewire::test(CreateMdTabela::class)
        ->fillForm([
            'modulo_id' => $modulo->id,
            'nome' => 'Pedido produto',
            'nome_fisico' => strtoupper($modulo->prefixo).'_PEDIDO_PRODUTO',
            'tipo' => TipoTabela::Ligacao->value,
            'exibe_filho' => $exibeFilho,
            'criar_telas' => true,
        ])
        ->call('create');

    return MdTabela::where('modulo_id', $modulo->id)->firstOrFail();
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $papel = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $papel->syncPermissions(Permission::all());
    $this->actingAs(tap(User::factory()->create())->assignRole($papel));
});

afterEach(function () {
    foreach (MdTabela::where('nome_fisico', 'like', '%_PEDIDO_PRODUTO')->get() as $tabela) {
        $modulo = $tabela->modulo;
        $tabela->delete();
        $modulo->delete();
    }
});

it('ao cadastrar a ligação exibindo o filho, não cria formulário nem gerenciamento', function () {
    $tabela = cadastrarLigacao(exibeFilho: true);

    expect($tabela->exibe_filho)->toBeTrue()
        ->and($tabela->formularios()->count())->toBe(0)
        ->and($tabela->gerenciamentos()->count())->toBe(0);
});

it('ao cadastrar a ligação sem exibir o filho, cria formulário e gerenciamento (sem arquivos)', function () {
    $tabela = cadastrarLigacao(exibeFilho: false);

    expect($tabela->exibe_filho)->toBeFalse()
        ->and($tabela->formularios()->count())->toBe(1)
        ->and($tabela->gerenciamentos()->count())->toBe(1)
        ->and(is_dir(app_path("Modulos/{$tabela->modulo->pasta}")))->toBeFalse();
});

it('as abas Formulários e Gerenciamentos só aparecem na ligação que não exibe o filho', function () {
    $modulo = Modulo::create(['nome' => 'Lig', 'prefixo' => 'l'.Str::lower(Str::random(3)), 'pasta' => 'Lig'.Str::random(5), 'banco' => 'central', 'roles' => []]);
    $tabela = MdTabela::create(['modulo_id' => $modulo->id, 'nome' => 'Pedido produto', 'nome_fisico' => strtoupper($modulo->prefixo).'_PEDIDO_PRODUTO', 'banco' => 'central', 'tipo' => 'ligacao', 'exibe_filho' => true]);

    Livewire::test(EditMdTabela::class, ['record' => $tabela->id])
        ->assertDontSee('Formulários')
        ->assertDontSee('Gerenciamentos')
        ->fillForm(['exibe_filho' => false])
        ->assertSee('Formulários')
        ->assertSee('Gerenciamentos');
});
