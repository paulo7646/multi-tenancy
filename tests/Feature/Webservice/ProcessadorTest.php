<?php

use App\Webservice\Models\Evento;
use App\Webservice\Models\Integracao;
use App\Webservice\Models\Operacao;
use App\Webservice\Models\Versao;
use App\Webservice\Models\Webservice;
use App\Webservice\Motor\ContextoEvento;
use App\Webservice\Motor\Processador;
use App\Webservice\Motor\ProcessamentoException;
use Illuminate\Support\Facades\DB;

/**
 * Monta root > clientes (lista, novo lote) com um evento. O executor dos testes
 * grava em "webservices" (como se fosse a tabela do evento) e falha no item "ruim".
 */
function layoutDeTeste(bool $loteAninhado = true, bool $clientesObrigatorio = true): array
{
    $ws = Webservice::create(['nome' => 'Entrada', 'tipo' => 'disponibilizar']);
    $op = Operacao::create(['webservice_id' => $ws->id, 'nome' => 'Novos', 'rota' => '/clientes', 'metodo' => 'POST']);
    $versao = Versao::create(['operacao_id' => $op->id, 'versao' => '1.0']);
    $root = $versao->objetos()->create(['nome' => 'root']);
    $clientes = $versao->objetos()->create([
        'nome' => 'clientes', 'pai_id' => $root->id, 'lista' => true,
        'novo_lote' => $loteAninhado, 'obrigatorio' => $clientesObrigatorio,
    ]);
    $clientes->eventos()->create(['tipo' => 'incluir', 'tabela' => 'X']);

    $integracao = Integracao::create(['operacao_id' => $op->id, 'status' => 'processando']);

    return [$integracao, $versao];
}

function executor(): Closure
{
    return function (Evento $evento, ContextoEvento $contexto) {
        $nome = $contexto->valor('nome');

        // grava e só depois falha: o rollback do lote tem que desfazer a gravação
        DB::table('webservices')->insert(['nome' => $nome, 'tipo' => 'consumir', 'autenticacao' => 'nenhuma', 'ativo' => true]);

        if ($nome === 'ruim') {
            throw new ProcessamentoException('item ruim');
        }
    };
}

it('processa uma lista inteira, um lote por item', function () {
    [$integracao, $versao] = layoutDeTeste();

    $status = (new Processador($integracao, $versao, executor()))
        ->processar(['clientes' => [['nome' => 'a'], ['nome' => 'b'], ['nome' => 'c']]]);

    expect($status)->toBe('concluida')
        ->and($integracao->fresh()->lotes_total)->toBe(3)
        ->and($integracao->lotes()->pluck('numero')->all())->toBe([1, 2, 3])
        ->and(DB::table('webservices')->whereIn('nome', ['a', 'b', 'c'])->count())->toBe(3);
});

it('desfaz só o lote que falhou e segue com os outros', function () {
    [$integracao, $versao] = layoutDeTeste();

    $status = (new Processador($integracao, $versao, executor()))
        ->processar(['clientes' => [['nome' => 'a'], ['nome' => 'ruim'], ['nome' => 'c']]]);

    $lotes = $integracao->lotes()->get();

    expect($status)->toBe('parcial')
        ->and($lotes->pluck('status')->all())->toBe(['ok', 'erro', 'ok'])
        ->and($lotes[1]->mensagem)->toBe('item ruim')
        ->and(DB::table('webservices')->where('nome', 'ruim')->exists())->toBeFalse()
        ->and(DB::table('webservices')->whereIn('nome', ['a', 'c'])->count())->toBe(2);
});

it('sem lote por item, um erro desfaz a integração inteira', function () {
    [$integracao, $versao] = layoutDeTeste(loteAninhado: false);

    $status = (new Processador($integracao, $versao, executor()))
        ->processar(['clientes' => [['nome' => 'a'], ['nome' => 'ruim'], ['nome' => 'c']]]);

    expect($status)->toBe('erro')
        ->and($integracao->lotes()->count())->toBe(1)
        ->and(DB::table('webservices')->whereIn('nome', ['a', 'ruim', 'c'])->exists())->toBeFalse();
});

it('objeto obrigatório ausente vira erro e não grava nada', function () {
    [$integracao, $versao] = layoutDeTeste();

    $status = (new Processador($integracao, $versao, executor()))->processar(['outro' => 1]);

    expect($status)->toBe('erro')
        ->and($integracao->lotes()->first()->mensagem)->toContain('clientes');
});

it('objeto opcional ausente é ignorado', function () {
    [$integracao, $versao] = layoutDeTeste(clientesObrigatorio: false);

    expect((new Processador($integracao, $versao, executor()))->processar(['outro' => 1]))->toBe('concluida');
});

it('aceita um objeto só no lugar de uma lista (XML com um elemento)', function () {
    [$integracao, $versao] = layoutDeTeste();

    (new Processador($integracao, $versao, executor()))->processar(['clientes' => ['nome' => 'unico']]);

    expect(DB::table('webservices')->where('nome', 'unico')->count())->toBe(1);
});

it('a versão sem objetos não processa', function () {
    $ws = Webservice::create(['nome' => 'Vazio']);
    $op = Operacao::create(['webservice_id' => $ws->id, 'nome' => 'Op', 'rota' => '/x', 'metodo' => 'POST']);
    $versao = Versao::create(['operacao_id' => $op->id, 'versao' => '1.0']);
    $integracao = Integracao::create(['operacao_id' => $op->id, 'status' => 'processando']);

    (new Processador($integracao, $versao, executor()))->processar([]);
})->throws(ProcessamentoException::class, 'não tem objetos');
