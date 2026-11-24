<?php

use App\Sistema\Exceptions\EventoException;
use App\Sistema\VQueryTabela;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\TabelasDeTeste;

/**
 * Caracterização do VQueryTabela: trava o que ele faz hoje (leitura e gravação por tipo, insert, update,
 * delete, tabelas filhas e transação) para a troca por BaseModel/BaseController/VQueryTabela não mudar o resultado.
 */
// a tabela é criada na transação do teste: a conexão central precisa ser a mesma para enxergá-la
beforeEach(fn () => compartilharConexaoCentral());

afterEach(fn () => TabelasDeTeste::limpar());

function produtoDeTeste(): array
{
    $modulo = TabelasDeTeste::modulo();
    $produto = TabelasDeTeste::tabela($modulo, 'produto', [
        ['DESCRICAO', 'string', ['tamanho' => 60, 'obrigatorio' => true]],
        ['PRECO', 'decimal', ['precisao' => 10, 'escala' => 2]],
        ['ATIVO', 'boolean'],
        ['VALIDADE', 'date'],
    ]);

    return [$modulo, $produto];
}

it('insere, lê e converte os valores pelo tipo do campo', function () {
    [$modulo] = produtoDeTeste();

    $novo = new VQueryTabela($modulo->prefixo, 'produto');
    $novo->setString('descricao', 'Suco')->setDecimal('preco', 9.9)->setBoolean('ativo', true)->setDate('validade', '2026-12-31')->insert();

    $lido = new VQueryTabela($modulo->prefixo, 'produto', $novo->getId());

    expect($lido->existe())->toBeTrue()
        ->and($lido->getString('descricao'))->toBe('Suco')
        ->and($lido->getDecimal('preco'))->toBe(9.9)
        ->and($lido->getBoolean('ativo'))->toBeTrue()
        ->and($lido->getDate('validade')->format('Y-m-d'))->toBe('2026-12-31')
        ->and($lido->getCampo('preco'))->toBe('9.9');
});

it('altera e exclui o registro', function () {
    [$modulo] = produtoDeTeste();

    $produto = new VQueryTabela($modulo->prefixo, 'produto');
    $produto->setString('descricao', 'Água')->insert();
    $id = $produto->getId();

    $produto->setString('descricao', 'Água com gás')->setDecimal('preco', 3)->update();

    expect((new VQueryTabela($modulo->prefixo, 'produto', $id))->getString('descricao'))->toBe('Água com gás');

    $produto->delete();

    expect($produto->existe())->toBeFalse()
        ->and(fn () => new VQueryTabela($modulo->prefixo, 'produto', $id))->toThrow(ModelNotFoundException::class);
});

it('recusa valor que não é do tipo do campo', function () {
    [$modulo] = produtoDeTeste();

    $produto = new VQueryTabela($modulo->prefixo, 'produto');

    expect(fn () => $produto->setDecimal('preco', 'abc'))->toThrow(EventoException::class)
        ->and(fn () => $produto->setDate('validade', 'ontem à noite'))->toThrow(EventoException::class)
        ->and(fn () => $produto->setString('inexistente', 'x'))->toThrow(EventoException::class);
});

it('traz as tabelas filhas, as escolhidas ou todas', function () {
    [$modulo, $produto] = produtoDeTeste();
    TabelasDeTeste::tabela($modulo, 'preco', [['VALOR', 'decimal', ['precisao' => 10, 'escala' => 2]]], $produto);

    $pai = new VQueryTabela($modulo->prefixo, 'produto');
    $pai->setString('descricao', 'Suco')->insert();

    foreach ([5, 7] as $valor) {
        $filho = new VQueryTabela($modulo->prefixo, 'preco');
        $filho->setForeignId('pai_id', $pai->getId())->setDecimal('valor', $valor)->insert();
    }

    $carregado = new VQueryTabela($modulo->prefixo, 'produto', $pai->getId());
    $carregado->setGetAllChildrens();

    $filhas = $carregado->getChildren(strtoupper("{$modulo->prefixo}_preco"));

    expect($filhas)->toHaveCount(2)
        ->and(array_map(fn (VQueryTabela $f) => $f->getDecimal('valor'), $filhas))->toBe([5.0, 7.0]);
});

it('desfaz a transação aberta com o rollback e mantém com o commit', function () {
    [$modulo] = produtoDeTeste();

    $a = new VQueryTabela($modulo->prefixo, 'produto');
    $a->iniciaTransacao()->setString('descricao', 'Desfeito')->insert();
    $a->transacaoRollBack();

    $b = new VQueryTabela($modulo->prefixo, 'produto');
    $b->iniciaTransacao()->setString('descricao', 'Mantido')->insert();
    $b->transacaoCommit();

    expect((new VQueryTabela($modulo->prefixo, 'produto'))->getModel()->newQuery()->pluck('DESCRICAO')->all())->toBe(['Mantido']);
});
