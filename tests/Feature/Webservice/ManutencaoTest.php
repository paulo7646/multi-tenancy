<?php

use App\Webservice\Models\Integracao;
use App\Webservice\Models\Operacao;
use App\Webservice\Models\Versao;
use App\Webservice\Models\Webservice;
use App\Webservice\Motor\Integrador;
use App\Webservice\Motor\ProcessamentoException;
use App\Webservice\Motor\Retencao;
use Illuminate\Support\Facades\Storage;

function operacaoSimples(): Operacao
{
    $ws = Webservice::create(['nome' => 'Entrada', 'tipo' => 'disponibilizar']);
    $op = Operacao::create(['webservice_id' => $ws->id, 'nome' => 'Novos', 'rota' => '/x', 'metodo' => 'POST']);
    Versao::create(['operacao_id' => $op->id, 'versao' => '1.0'])->objetos()->create(['nome' => 'root']);

    return $op;
}

it('apaga integrações antigas e os arquivos delas, e mantém as recentes', function () {
    Storage::fake('local');
    $op = operacaoSimples();

    $antiga = Integracao::create(['operacao_id' => $op->id, 'status' => 'concluida', 'arquivo' => 'webservice/integracoes/1.json', 'finalizada_em' => now()->subDays(100)]);
    $recente = Integracao::create(['operacao_id' => $op->id, 'status' => 'concluida', 'arquivo' => 'webservice/integracoes/2.json', 'finalizada_em' => now()->subDays(5)]);
    $andamento = Integracao::create(['operacao_id' => $op->id, 'status' => 'processando']);
    Storage::disk('local')->put($antiga->arquivo, '{}');
    Storage::disk('local')->put($recente->arquivo, '{}');

    expect(Retencao::limpar(90))->toBe(1)
        ->and(Integracao::find($antiga->id))->toBeNull()
        ->and(Integracao::find($recente->id))->not->toBeNull()
        ->and(Integracao::find($andamento->id))->not->toBeNull();

    Storage::disk('local')->assertMissing('webservice/integracoes/1.json');
    Storage::disk('local')->assertExists('webservice/integracoes/2.json');
});

it('reprocessa o arquivo de uma integração em uma integração nova', function () {
    Storage::fake('local');
    $op = operacaoSimples();

    $original = Integrador::receber($op, '{"a":1}');
    $nova = Integrador::reprocessar($original);

    expect($nova->id)->not->toBe($original->id)
        ->and($nova->status)->toBe('concluida')
        ->and($nova->conteudo())->toBe('{"a":1}');
});

it('não reprocessa quando o arquivo não existe mais', function () {
    Storage::fake('local');
    $op = operacaoSimples();

    Integrador::reprocessar(Integracao::create(['operacao_id' => $op->id, 'status' => 'erro']));
})->throws(ProcessamentoException::class, 'arquivo');

it('o limite de chamadas da entrada responde 429', function () {
    config(['webservice.limite_por_minuto' => 2]);
    $op = operacaoSimples();
    $url = rtrim(config('app.url'), '/')."/ws/{$op->webservice_id}/x";

    $this->postJson($url, [])->assertOk();
    $this->postJson($url, [])->assertOk();
    $this->postJson($url, [])->assertStatus(429);
});
