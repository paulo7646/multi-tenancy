<?php

use App\Models\Md\MdCampo;
use App\Sistema\Exceptions\EventoException;
use App\Sistema\Registro;
use App\Sistema\VQueryTabela;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Support\ControladorQueRegistra;
use Tests\Support\TabelasDeTeste;

/**
 * Ganchos do controller (ordem, cancelamento, VQueryTabela passando por ele) e persiste() do model
 * (por campo chave, com filhos recursivos).
 */
beforeEach(function () {
    compartilharConexaoCentral();
    ControladorQueRegistra::$log = [];
});

afterEach(fn () => TabelasDeTeste::limpar());

function cadastroComControlador(): array
{
    $modulo = TabelasDeTeste::modulo();
    $tabela = TabelasDeTeste::tabela($modulo, 'cliente', [['NOME', 'string', ['tamanho' => 40]]]);
    app()->bind($tabela->controllerFqcn(), fn () => new ControladorQueRegistra);

    return [$modulo, $tabela];
}

it('roda os ganchos do controller na ordem ao incluir e ao alterar pelo VQueryTabela', function () {
    [$modulo] = cadastroComControlador();

    $registro = (new VQueryTabela($modulo->prefixo, 'cliente'))->setString('nome', 'Ana')->insert();

    // sem transação do teste por fora, os afterAfter rodam no commit; aqui o teste já está numa transação,
    // então só conferimos os ganchos de dentro da transação, na ordem
    expect(array_slice(ControladorQueRegistra::$log, 0, 5))->toBe([
        'beforeBeforePost', 'beforePost', 'beforeInsert', 'afterInsert', 'afterPost',
    ]);

    ControladorQueRegistra::$log = [];
    $registro->setString('nome', 'Bia')->update();

    expect(array_slice(ControladorQueRegistra::$log, 0, 5))->toBe([
        'beforeBeforePost', 'beforePost', 'beforeUpdate', 'afterUpdate', 'afterPost',
    ]);

    ControladorQueRegistra::$log = [];
    $registro->delete();

    expect(array_slice(ControladorQueRegistra::$log, 0, 3))->toBe(['beforeBeforeDelete', 'beforeDelete', 'afterDelete']);
});

it('cancela a inclusão quando um before devolve false e não grava nada', function () {
    [$modulo, $tabela] = cadastroComControlador();

    $novo = (new VQueryTabela($modulo->prefixo, 'cliente'))->setString('nome', 'CANCELAR');

    expect(fn () => $novo->insert())->toThrow(EventoException::class)
        ->and(ControladorQueRegistra::$log)->not->toContain('afterInsert')
        ->and(Registro::model($tabela)->newQuery()->count())->toBe(0);
});

it('gravar direto com save() não passa pelo controller', function () {
    [, $tabela] = cadastroComControlador();

    $model = Registro::model($tabela);
    $model->NOME = 'Direto';
    $model->save();

    expect(ControladorQueRegistra::$log)->toBe([]);
});

it('localiza pelo campo chave com set() e persiste()', function () {
    $modulo = TabelasDeTeste::modulo();
    $tabela = TabelasDeTeste::tabela($modulo, 'produto', [
        ['CODIGO', 'string', ['tamanho' => 20, 'chave' => true]],
        ['DESCRICAO', 'string', ['tamanho' => 60]],
    ]);

    $campo = MdCampo::where('md_tabela_id', $tabela->id)->where('nome_fisico', 'CODIGO')->first();
    expect($campo->unico)->toBeTrue()->and($campo->obrigatorio)->toBeTrue();

    $criado = Registro::model($tabela);
    $criado->CODIGO = 'A-10';
    $criado->DESCRICAO = 'Suco';
    $criado->save();

    $lido = Registro::model($tabela)->set('CODIGO', 'A-10')->persiste();

    expect($lido->DESCRICAO)->toBe('Suco')->and($lido->exists)->toBeTrue()
        ->and(fn () => Registro::model($tabela)->set('CODIGO', 'ZZZ')->persiste())->toThrow(ModelNotFoundException::class);
});

it('só aceita um campo chave por tabela', function () {
    $modulo = TabelasDeTeste::modulo();
    $tabela = TabelasDeTeste::tabela($modulo, 'produto', [['CODIGO', 'string', ['tamanho' => 20, 'chave' => true]]]);

    expect(fn () => MdCampo::create([
        'md_tabela_id' => $tabela->id, 'nome' => 'Outro', 'nome_fisico' => 'OUTRO', 'tipo' => 'string', 'tamanho' => 10, 'chave' => true,
    ]))->toThrow(ValidationException::class);
});

it('persiste(true) traz filhos e netos pela FK com uma consulta por tabela e nível', function () {
    $modulo = TabelasDeTeste::modulo();
    $pedido = TabelasDeTeste::tabela($modulo, 'pedido', [['NUMERO', 'string', ['tamanho' => 20, 'chave' => true]]]);
    $item = TabelasDeTeste::tabela($modulo, 'item', [['NOME', 'string', ['tamanho' => 20]]], $pedido);
    $detalhe = TabelasDeTeste::tabela($modulo, 'detalhe', [['NOME', 'string', ['tamanho' => 20]]], $item);

    $p = Registro::model($pedido);
    $p->NUMERO = 'P1';
    $p->save();

    foreach (range(1, 5) as $i) {
        $it = Registro::model($item);
        $it->NOME = "item {$i}";
        $it->PAI_ID = $p->id;
        $it->save();

        foreach (range(1, 5) as $j) {
            $d = Registro::model($detalhe);
            $d->NOME = "detalhe {$i}.{$j}";
            $d->PAI_ID = $it->id;
            $d->save();
        }
    }

    $semFilhos = Registro::model($pedido)->set('NUMERO', 'P1')->persiste();
    expect($semFilhos->getRelations())->toBe([]);

    $carregado = Registro::model($pedido)->set('NUMERO', 'P1')->persiste(true);

    $itens = $carregado->getRelation($item->nome_fisico);

    expect($itens)->toHaveCount(5)
        ->and($itens[0]->getRelation($detalhe->nome_fisico))->toHaveCount(5)
        ->and(collect($itens)->sum(fn ($i) => count($i->getRelation($detalhe->nome_fisico))))->toBe(25);
});
