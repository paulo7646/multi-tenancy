<?php

// TEMPORÁRIO: dicionário v2 (módulo "tt", base pai; limpo depois).

use App\Filament\Resources\Dicionario\MdFormularioResource\Pages\EditMdFormulario;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\CreateMdTabela;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\EditMdTabela;
use App\Filament\Resources\Dicionario\ModuloResource\Pages\CreateModulo;
use App\Filament\Sistema\Pages\FormularioPage;
use App\Filament\Sistema\Pages\GerenciamentoPage;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdTabela;
use App\Models\Md\MdTabelaTraducao;
use App\Models\Md\Modulo;
use App\Models\User;
use App\Sistema\ModelProperty;
use App\Sistema\Registro;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function () {
    Registro::limpar();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::role('super_admin')->firstOrFail());
});

function v2Campo(string $nome, string $fisico, string $tipo, array $extra = []): array
{
    $uid = (string) Str::uuid();

    return [$uid => ['uid' => $uid, 'nome' => $nome, 'nome_fisico' => $fisico, 'tipo' => $tipo, 'fk_on_delete' => 'restrict', 'sistema' => false, ...$extra]];
}

function v2Uid(string $tabela, string $campo): string
{
    return MdTabela::where('nome_fisico', $tabela)->first()->campos()->where('nome_fisico', $campo)->value('uid');
}

it('1. módulo e tabela LISTA: nome em maiúscula, campos do sistema, enum e registros', function () {
    Livewire::test(CreateModulo::class)->fillForm(['nome' => 'Teste Novo', 'prefixo' => 'tt', 'pasta' => 'TesteNovo', 'banco' => 'central'])->call('create')->assertHasNoFormErrors();
    $modulo = Modulo::where('prefixo', 'tt')->firstOrFail();

    Livewire::test(CreateMdTabela::class)->fillForm([
        'modulo_id' => $modulo->id, 'nome' => 'Status', 'nome_fisico' => 'tt_status', 'nome_controller' => 'ControllerStatus', 'tipo' => 'lista',
        'icone' => 'heroicon-o-flag', 'cor' => 'red', 'criar_telas' => true,
        'itens' => ['a' => ['descricao' => 'Aberto'], 'b' => ['descricao' => 'Em análise']],
    ])->call('create')->assertHasNoFormErrors();

    $tabela = MdTabela::where('nome_fisico', 'TT_STATUS')->firstOrFail();
    expect($tabela->campos()->where('sistema', true)->pluck('nome_fisico')->all())->toBe(['CODIGO', 'DESCRICAO'])
        ->and(DB::connection('central')->table('TT_STATUS')->orderBy('CODIGO')->pluck('DESCRICAO', 'CODIGO')->all())->toBe([1 => 'Aberto', 2 => 'Em análise']);

    $enum = file_get_contents(app_path('Modulos/TesteNovo/Enums/Status.php'));
    expect($enum)->toContain('enum Status: int implements HasLabel')->toContain('case ABERTO = 1;')->toContain('case EM_ANALISE = 2;');
});

it('2. lista: código sequencial não é reaproveitado; o enum e os registros acompanham', function () {
    $tabela = MdTabela::where('nome_fisico', 'TT_STATUS')->firstOrFail();
    $itens = $tabela->itens()->get()->mapWithKeys(fn ($i) => ["record-{$i->id}" => ['descricao' => $i->descricao]])->all();
    $primeiro = array_key_first($itens);
    unset($itens[$primeiro]); // tira "Aberto"
    $itens['novo'] = ['descricao' => 'Fechado'];

    Livewire::test(EditMdTabela::class, ['record' => $tabela->getRouteKey()])->set('data.itens', $itens)->call('save')->assertHasNoFormErrors();

    expect(DB::connection('central')->table('TT_STATUS')->orderBy('CODIGO')->pluck('DESCRICAO', 'CODIGO')->all())->toBe([2 => 'Em análise', 3 => 'Fechado'])
        ->and(file_get_contents(app_path('Modulos/TesteNovo/Enums/Status.php')))->toContain('case FECHADO = 3;')->not->toContain('ABERTO');

    $utils = new class { use App\Sistema\Base\Concerns\Utilidades; };
    $enum = $utils->getEnum('tt', 'status');
    expect($enum::from(3)->getLabel())->toBe('Fechado')->and($enum::opcoes())->toBe([2 => 'Em análise', 3 => 'Fechado']);
});

it('3. conteúdo: o valor é do usuário (enum de texto)', function () {
    $modulo = Modulo::where('prefixo', 'tt')->firstOrFail();

    Livewire::test(CreateMdTabela::class)->fillForm([
        'modulo_id' => $modulo->id, 'nome' => 'Origem', 'nome_fisico' => 'TT_ORIGEM', 'nome_controller' => 'ControllerOrigem', 'tipo' => 'conteudo',
        'itens' => ['a' => ['valor' => 'WEB', 'descricao' => 'Site'], 'b' => ['valor' => 'LOJA', 'descricao' => 'Loja física']],
    ])->call('create')->assertHasNoFormErrors();

    expect(DB::connection('central')->table('TT_ORIGEM')->orderBy('VALOR')->pluck('DESCRICAO', 'VALOR')->all())->toBe(['LOJA' => 'Loja física', 'WEB' => 'Site'])
        ->and(file_get_contents(app_path('Modulos/TesteNovo/Enums/Origem.php')))->toContain("enum Origem: string")->toContain("case SITE = 'WEB';");
});

it('4. traduções com nome na tabela apontada', function () {
    $tabela = MdTabela::where('nome_fisico', 'TT_STATUS')->firstOrFail();
    $codigo = v2Uid('TT_STATUS', 'CODIGO');
    $descricao = v2Uid('TT_STATUS', 'DESCRICAO');

    Livewire::test(EditMdTabela::class, ['record' => $tabela->getRouteKey()])->fillForm([
        'traducoesLookup' => [
            'a' => ['nome' => 'Código e descrição', 'padrao' => true, 'campos' => ['x' => ['campo' => $codigo], 'y' => ['campo' => $descricao]]],
            'b' => ['nome' => 'Só descrição', 'padrao' => false, 'campos' => ['z' => ['campo' => $descricao]]],
        ],
    ])->call('save')->assertHasNoFormErrors();

    expect($tabela->traducoesLookup()->pluck('nome')->all())->toBe(['Código e descrição', 'Só descrição'])
        ->and($tabela->traducoesLookup()->where('nome', 'Só descrição')->first()->campos)->toBe([$descricao]);
});

it('5. cadastro com FK e campo sem componente; componentes com tipo, máscara, tradução e parâmetros', function () {
    $modulo = Modulo::where('prefixo', 'tt')->firstOrFail();
    $status = MdTabela::where('nome_fisico', 'TT_STATUS')->firstOrFail();

    Livewire::test(CreateMdTabela::class)->fillForm([
        'modulo_id' => $modulo->id, 'nome' => 'Pedido', 'nome_fisico' => 'tt_pedido', 'nome_controller' => 'ControllerPedido', 'criar_telas' => true,
        'icone' => 'heroicon-o-shopping-cart', 'cor' => 'emerald',
        'campos' => [
            ...v2Campo('Descrição', 'descricao', 'string', ['obrigatorio' => true]),
            ...v2Campo('Status', 'status_id', 'foreignId', ['fk_tabela_id' => $status->id]),
            ...v2Campo('CPF do cliente', 'cpf_cliente', 'string', ['tamanho' => 14]),
            ...v2Campo('Entrega', 'entrega', 'dateTime'),
            ...v2Campo('Interno', 'interno', 'string', ['sem_componente' => true]),
        ],
    ])->call('create')->assertHasNoFormErrors();

    $pedido = MdTabela::where('nome_fisico', 'TT_PEDIDO')->firstOrFail();
    expect($pedido->campos()->pluck('nome_fisico')->all())->toBe(['DESCRICAO', 'STATUS_ID', 'CPF_CLIENTE', 'ENTREGA', 'INTERNO'])
        ->and(Illuminate\Support\Facades\Schema::connection('central')->getColumnListing('TT_PEDIDO'))->toContain('INTERNO');

    $form = MdFormulario::where('md_tabela_id', $pedido->id)->firstOrFail();
    // layout padrão sem o campo sem componente
    expect(App\Sistema\Runtime\Layout::uids($form->layout()))->not->toContain(v2Uid('TT_PEDIDO', 'INTERNO'));

    $soDescricao = MdTabelaTraducao::where('nome', 'Só descrição')->value('id');
    $u = fn ($c) => v2Uid('TT_PEDIDO', $c);

    $tela = Livewire::test(EditMdFormulario::class, ['record' => $form->getRouteKey()]);
    $estado = $tela->get('data.componentes');
    $chave = fn ($uid) => collect($estado)->search(fn ($i) => $i['campo'] === $uid);

    // padrão: os campos na ordem, sem o "sem componente"; tradução só no componente Tabela da FK
    expect(collect($estado)->pluck('campo')->values()->all())->toBe([$u('DESCRICAO'), $u('STATUS_ID'), $u('CPF_CLIENTE'), $u('ENTREGA')])
        ->and(collect($estado)->pluck('componente')->values()->all())->toBe(['texto', 'lookup', 'texto', 'data_hora']);
    $tela->assertFormFieldDisabled("componentes.{$chave($u('CPF_CLIENTE'))}.traducao_id")
        ->assertFormFieldEnabled("componentes.{$chave($u('STATUS_ID'))}.traducao_id")
        ->assertFormFieldDisabled("componentes.{$chave($u('ENTREGA'))}.mascara")
        ->assertFormFieldEnabled("componentes.{$chave($u('CPF_CLIENTE'))}.mascara");

    // obrigatório sem padrão fora do formulário: não deixa
    $tela->set('data.componentes', ['x' => ['campo' => $u('CPF_CLIENTE'), 'componente' => 'texto', 'colunas' => 1]])
        ->call('save')->assertHasFormErrors(['componentes']);

    $tela->set('data.componentes', [
        'a' => ['campo' => $u('CPF_CLIENTE'), 'componente' => 'texto', 'mascara' => '999.999.999-99', 'colunas' => 1, 'largura' => 300],
        'b' => ['campo' => $u('STATUS_ID'), 'componente' => 'lookup', 'traducao_id' => $soDescricao, 'mascara' => 'lixo', 'colunas' => 1, 'quebra' => true],
        'c' => ['campo' => $u('ENTREGA'), 'componente' => 'data_hora_segundo', 'colunas' => 1, 'completar' => true],
        'd' => ['campo' => $u('DESCRICAO'), 'componente' => 'memo', 'obrigatorio' => false, 'colunas' => 1, 'altura' => 120],
    ])->call('save')->assertHasNoFormErrors();

    $componentes = $form->componentes()->get();
    expect($componentes->pluck('campo')->all())->toBe([$u('CPF_CLIENTE'), $u('STATUS_ID'), $u('ENTREGA'), $u('DESCRICAO')])
        ->and($componentes->pluck('tipo')->unique()->all())->toBe(['campo'])
        ->and($componentes[0]->mascara)->toBe('999.999.999-99')
        ->and($componentes[0]->largura)->toBe(300)
        ->and($componentes[1]->traducao_id)->toBe($soDescricao)
        ->and($componentes[1]->mascara)->toBeNull()
        ->and($componentes[1]->quebra)->toBeTrue()
        ->and($componentes[2]->completar)->toBeTrue()
        ->and($componentes[3]->obrigatorio)->toBeFalse()
        ->and($componentes[3]->altura)->toBe(120);

    // ao abrir de novo, o que foi gravado volta
    $estado = Livewire::test(EditMdFormulario::class, ['record' => $form->getRouteKey()])->get('data.componentes');
    expect(collect($estado)->pluck('componente')->values()->all())->toBe(['texto', 'lookup', 'data_hora_segundo', 'memo'])
        ->and(collect($estado)->pluck('obrigatorio')->values()->all())->toBe([false, false, false, false]);
});

it('6. o formulário de verdade usa tudo isso', function () {
    DB::connection('central')->table('TT_STATUS')->get(); // registros existem (lista)

    $tela = Livewire::test(FormularioPage::class, ['gerenciamento' => 'TT_PEDIDO', 'registro' => 'novo']);
    $componentes = collect($tela->instance()->form->getFlatComponents());
    $campo = fn (string $n) => $componentes->first(fn ($c) => $c instanceof Field && $c->getName() === $n);

    expect($campo('CPF_CLIENTE'))->toBeInstanceOf(TextInput::class)
        ->and($campo('CPF_CLIENTE')->getMask())->toBe('999.999.999-99')
        ->and($campo('ENTREGA'))->toBeInstanceOf(DateTimePicker::class)
        ->and($campo('ENTREGA')->hasSeconds())->toBeTrue()
        ->and($campo('DESCRICAO'))->toBeInstanceOf(\Filament\Forms\Components\Textarea::class)
        ->and($campo('DESCRICAO')->isRequired())->toBeFalse()
        ->and($campo('INTERNO'))->toBeNull()
        // tradução escolhida: só a descrição
        ->and(array_values($campo('STATUS_ID')->getOptions()))->toBe(['Em análise', 'Fechado']);

    $this->get('http://multi-tenancy.test/admin/m/TT_PEDIDO/r/novo')->assertOk()->assertSee('Entrega');

    // formulário novo da mesma tabela: já vem com os componentes sugeridos
    $novo = Livewire::test(App\Filament\Resources\Dicionario\MdFormularioResource\Pages\CreateMdFormulario::class)->set('data.md_tabela_id', MdTabela::where('nome_fisico', 'TT_PEDIDO')->value('id'));
    expect($novo->get('data.componentes'))->toHaveCount(4);
    $novo->call('create')->assertHasNoFormErrors();
    expect(MdFormulario::where('nome_fisico', 'TT_PEDIDO_2')->firstOrFail()->componentes()->count())->toBe(4);
});

it('7. lista é só leitura no datagrid; ModelProperty e controller', function () {
    Livewire::test(GerenciamentoPage::class, ['gerenciamento' => 'TT_STATUS'])->assertActionHidden('incluir');

    $p = (new ModelProperty('tt', 'pedido'))->setString('descricao', 'Pedido 1')->setForeignId('status_id', 2)->setString('interno', 'segredo')->insert();
    expect((new ModelProperty('TT', 'TT_PEDIDO', $p->getId()))->getString('INTERNO'))->toBe('segredo')
        ->and($p->getController())->toBeInstanceOf(App\Modulos\TesteNovo\TT_PEDIDO\ControllerPedido::class)
        ->and($p->getController('tt', 'status'))->toBeInstanceOf(App\Modulos\TesteNovo\TT_STATUS\ControllerStatus::class);
});

it('8. menu: grupo do módulo sem ícone, itens com o ícone e a cor da tabela', function () {
    $html = $this->get('http://multi-tenancy.test/admin')->assertOk()->getContent();
    $d = new DOMDocument(); @$d->loadHTML($html); $x = new DOMXPath($d);

    $grupo = null;
    foreach ($x->query('//nav//li[contains(@class,"fi-sidebar-group")]') as $g) {
        if (str_contains($g->textContent, 'Teste Novo')) {
            $grupo = $g;
        }
    }

    expect($grupo)->not->toBeNull()
        ->and($x->query('.//*[contains(@class,"fi-sidebar-group-icon")]', $grupo)->length)->toBe(0)
        ->and($x->query('.//*[contains(@class,"fi-sidebar-item-grouped-border")]', $grupo)->length)->toBe(0)
        ->and($d->saveHTML($grupo))->toContain('Visão geral')->toContain('Pedido')->toContain('color: ' . App\Sistema\Enums\Cor::css('emerald'));
});

it('9. md:importar mantém componentes, traduções e itens', function () {
    $contar = fn () => [
        App\Models\Md\MdFormularioComponente::count(), MdTabelaTraducao::count(), App\Models\Md\MdTabelaItem::count(),
        App\Models\Md\MdFormularioComponente::whereNotNull('traducao_id')->count(),
    ];
    $antes = $contar();

    $this->artisan('md:importar')->assertSuccessful();

    expect($contar())->toBe($antes);
});
