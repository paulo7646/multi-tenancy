<?php

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Enums\TipoTabela;
use Illuminate\Validation\ValidationException;

/**
 * Uma tabela só pode referenciar tabelas do próprio módulo ou de um módulo padrão (Estrutura, que todo
 * cliente tem). Referência a outro módulo geraria redundância: o cliente pode nem ter aquele módulo.
 */
beforeEach(fn () => compartilharConexaoCentral());

function moduloDoDicionario(string $prefixo, bool $padrao = false): Modulo
{
    return Modulo::create(['nome' => ucfirst($prefixo), 'prefixo' => $prefixo, 'pasta' => ucfirst($prefixo), 'banco' => Banco::Tenant, 'padrao' => $padrao]);
}

function tabelaDoModulo(Modulo $modulo, string $nome): MdTabela
{
    return MdTabela::create([
        'modulo_id' => $modulo->id, 'nome' => $nome, 'nome_fisico' => strtoupper($modulo->prefixo.'_'.$nome),
        'banco' => Banco::Tenant, 'tipo' => TipoTabela::Cadastro,
    ]);
}

function campoFk(MdTabela $origem, MdTabela $destino): MdCampo
{
    return MdCampo::create([
        'md_tabela_id' => $origem->id, 'nome' => 'Destino', 'nome_fisico' => 'DESTINO_ID',
        'tipo' => TipoCampo::ForeignId, 'fk_tabela_id' => $destino->id, 'fk_on_delete' => 'restrict',
    ]);
}

it('referencia tabela do próprio módulo', function () {
    $vendas = moduloDoDicionario('ven');

    expect(campoFk(tabelaDoModulo($vendas, 'pedido'), tabelaDoModulo($vendas, 'cliente')))->toBeInstanceOf(MdCampo::class);
});

it('referencia tabela do módulo padrão (Estrutura)', function () {
    $estrutura = moduloDoDicionario('est', padrao: true);
    $vendas = moduloDoDicionario('ven');

    expect(campoFk(tabelaDoModulo($vendas, 'pedido'), tabelaDoModulo($estrutura, 'filial')))->toBeInstanceOf(MdCampo::class);
});

it('não referencia tabela de outro módulo que não é padrão', function () {
    $vendas = moduloDoDicionario('ven');
    $estoque = moduloDoDicionario('esq');

    expect(fn () => campoFk(tabelaDoModulo($vendas, 'pedido'), tabelaDoModulo($estoque, 'produto')))
        ->toThrow(ValidationException::class, 'outro módulo');
});

it('o módulo padrão também não referencia módulos de cliente', function () {
    $estrutura = moduloDoDicionario('est', padrao: true);
    $vendas = moduloDoDicionario('ven');

    expect(fn () => campoFk(tabelaDoModulo($estrutura, 'filial'), tabelaDoModulo($vendas, 'pedido')))
        ->toThrow(ValidationException::class);
});

it('o módulo não deixa de ser padrão enquanto outro módulo aponta para as tabelas dele', function () {
    $estrutura = moduloDoDicionario('est', padrao: true);
    $vendas = moduloDoDicionario('ven');
    campoFk(tabelaDoModulo($vendas, 'pedido'), tabelaDoModulo($estrutura, 'filial'));

    expect(fn () => $estrutura->update(['padrao' => false]))->toThrow(ValidationException::class, 'Desfaça essas referências');
});

it('mudar a tabela de módulo revalida as referências que ela faz e as que recebe', function () {
    $vendas = moduloDoDicionario('ven');
    $estoque = moduloDoDicionario('esq');
    $outro = moduloDoDicionario('out');

    $cliente = tabelaDoModulo($vendas, 'cliente');
    $pedido = tabelaDoModulo($vendas, 'pedido');
    campoFk($pedido, $cliente); // pedido -> cliente, os dois em Vendas

    // cliente sai de Vendas: pedido (que continua lá) deixaria de poder apontar para ele
    expect(fn () => $cliente->update(['modulo_id' => $estoque->id]))->toThrow(ValidationException::class, 'outro módulo');

    // pedido sai de Vendas: ele aponta para cliente, que ficou em Vendas
    expect(fn () => $pedido->update(['modulo_id' => $outro->id]))->toThrow(ValidationException::class);

    // uma tabela sem nenhuma referência pode mudar de módulo
    $solta = tabelaDoModulo($outro, 'solta');
    expect($solta->update(['modulo_id' => $vendas->id]))->toBeTrue();
});

it('as tabelas oferecidas para referência são só as do módulo e as do módulo padrão', function () {
    $estrutura = moduloDoDicionario('est', padrao: true);
    $vendas = moduloDoDicionario('ven');
    $estoque = moduloDoDicionario('esq');

    $propria = tabelaDoModulo($vendas, 'cliente');
    $padrao = tabelaDoModulo($estrutura, 'filial');
    $deOutro = tabelaDoModulo($estoque, 'produto');

    $ids = MdTabela::query()->permitidasPara($vendas->id)->pluck('id')->all();

    expect($ids)->toContain($propria->id, $padrao->id)->not->toContain($deOutro->id);
});
