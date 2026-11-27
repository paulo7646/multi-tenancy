<?php

use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdGerenciamentoBotao;
use App\Models\User;
use App\Sistema\Base\BaseResource;
use App\Sistema\Geradores\Exportador;
use App\Sistema\Registro;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\TabelasDeTeste;

/**
 * Botões: todos no topo do datagrid (nada na linha), em ordem, com grupos; multioperação ou um registro.
 */
beforeEach(fn () => compartilharConexaoCentral());

afterEach(fn () => TabelasDeTeste::limpar());

function botaoDeTeste(array $dados): MdGerenciamentoBotao
{
    return (new MdGerenciamentoBotao)->forceFill(['id' => $dados['ordem'] ?? 0, 'titulo' => $dados['nome_fisico'], 'nome_arquivo' => 'Botoes', 'cor' => 'primary', ...$dados]);
}

it('mistura botões e grupos pela ordem, e cada grupo vira um botão com os dele', function () {
    $botoes = [
        botaoDeTeste(['nome_fisico' => 'c', 'ordem' => 30]),
        botaoDeTeste(['nome_fisico' => 'a', 'ordem' => 10]),
        botaoDeTeste(['nome_fisico' => 'g2', 'ordem' => 2, 'grupo' => 'mais', 'exige_selecao' => true]),
        botaoDeTeste(['nome_fisico' => 'g1', 'ordem' => 1, 'grupo' => 'mais', 'exige_selecao' => true]),
        botaoDeTeste(['nome_fisico' => 'x', 'ordem' => 5, 'grupo' => 'nao_existe']),
    ];

    $acao = fn (MdGerenciamentoBotao $b) => ($b->exige_selecao ? BulkAction::make($b->nome_fisico) : Action::make($b->nome_fisico));
    $itens = BaseResource::agruparBotoes($botoes, [['nome' => 'mais', 'titulo' => 'Mais ações', 'ordem' => 20]], $acao);

    // sem grupo cadastrado o botão fica de fora; o grupo entra na posição da ordem dele (20)
    expect(array_map(fn ($i) => $i instanceof ActionGroup ? 'grupo:'.$i->getLabel() : $i->getName(), $itens))
        ->toBe(['x', 'a', 'grupo:Mais ações', 'c'])
        ->and($itens[2])->toBeInstanceOf(BulkActionGroup::class)
        ->and(array_map(fn ($a) => $a->getName(), $itens[2]->getActions()))->toBe(['g1', 'g2']);
});

it('o datagrid não tem botões na linha: alterar, imprimir, excluir e os cadastrados ficam no topo', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(tap(User::factory()->create())->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])));

    $modulo = TabelasDeTeste::modulo();
    $tabela = TabelasDeTeste::tabela($modulo, 'cliente', [['NOME', 'string', ['tamanho' => 40]]]);
    $formulario = MdFormulario::create(['md_tabela_id' => $tabela->id, 'nome' => 'cliente', 'titulo' => 'Cliente', 'nome_fisico' => 'ZZ_CLIENTE', 'nome_arquivo' => 'ViewCliente']);
    $gerenciamento = MdGerenciamento::create([
        'md_tabela_id' => $tabela->id, 'md_formulario_id' => $formulario->id, 'nome' => 'cliente', 'titulo' => 'Cliente',
        'nome_fisico' => 'ZZ_CLIENTE', 'nome_arquivo' => 'ClienteResource', 'grupos_botoes' => [['nome' => 'mais', 'titulo' => 'Mais', 'ordem' => 1]],
    ]);
    $gerenciamento->botoes()->create(['nome' => 'Faturar', 'titulo' => 'Faturar', 'nome_fisico' => 'faturar', 'nome_arquivo' => 'BotoesCliente', 'exige_selecao' => true, 'multiplos' => true]);
    $gerenciamento->botoes()->create(['nome' => 'Avisar', 'titulo' => 'Avisar', 'nome_fisico' => 'avisar', 'nome_arquivo' => 'BotoesCliente', 'grupo' => 'mais']);

    $model = Registro::model($tabela);
    $model->NOME = 'Ana';
    $model->save();

    $resource = Registro::resource($gerenciamento->fresh('tabela.modulo'));

    // a tela em memória nasceu depois do boot do painel: registra as rotas dela como o painel faria
    Route::name('filament.admin.')->prefix('admin')
        ->group(fn () => $resource::registerRoutes(Filament::getPanel('admin')));
    app('router')->getRoutes()->refreshNameLookups();

    Livewire::test($resource.'\\Pages\\Listar')
        ->assertCanSeeTableRecords([$model])
        ->assertTableBulkActionExists('alterar')
        ->assertTableBulkActionExists('excluir_selecionados')
        ->assertTableBulkActionExists('faturar')
        ->assertTableActionDoesNotExist('edit', record: $model)
        ->assertTableActionDoesNotExist('delete', record: $model)
        ->assertTableActionDoesNotExist('selecionar', record: $model);
});

it('na lista de seleção da lupa o duplo clique devolve o registro ao campo', function () {
    request()->query->replace(['janela' => '1', 'modo' => 'selecionar', 'campo' => 'data.CLIENTE_ID', 'recurso' => 'cliente']);

    expect(view('components.janela-estilos')->render())
        ->toContain('dblclick')
        ->toContain('fk-selecionado')
        ->toContain('data.CLIENTE_ID');
});

it('sem o recurso na URL (página aberta por outro caminho) o duplo clique não seleciona', function () {
    request()->query->replace(['janela' => '1', 'modo' => 'selecionar', 'campo' => 'data.CLIENTE_ID']);

    expect(view('components.janela-estilos')->render())->not->toContain('dblclick');
});

it('o dicionário exportado antes (botão na linha) é importado no modelo novo', function () {
    $converter = fn (array $b) => Exportador::botaoDoModeloAntigo($b);

    expect($converter(['nome_fisico' => 'a', 'no_registro' => true, 'exige_selecao' => false]))
        ->toMatchArray(['exige_selecao' => true, 'multiplos' => false])->not->toHaveKey('no_registro')
        ->and($converter(['nome_fisico' => 'b', 'no_registro' => false, 'exige_selecao' => true, 'multiplos' => false]))
        ->toMatchArray(['exige_selecao' => true, 'multiplos' => true])
        ->and($converter(['nome_fisico' => 'c', 'no_registro' => false, 'exige_selecao' => false]))
        ->toMatchArray(['exige_selecao' => false, 'multiplos' => false])
        ->and($converter(['nome_fisico' => 'd', 'exige_selecao' => true, 'multiplos' => false]))
        ->toMatchArray(['exige_selecao' => true, 'multiplos' => false]);
});
