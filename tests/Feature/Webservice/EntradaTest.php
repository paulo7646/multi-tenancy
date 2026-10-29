<?php

use App\Webservice\Models\Operacao;
use App\Webservice\Models\Versao;
use App\Webservice\Models\Webservice;

function novaEntrada(string $autenticacao, array $credenciais = []): Webservice
{
    $ws = Webservice::create(['nome' => 'Entrada', 'tipo' => 'disponibilizar', 'autenticacao' => $autenticacao, 'credenciais' => $credenciais]);
    $op = Operacao::create(['webservice_id' => $ws->id, 'nome' => 'Novos', 'rota' => '/clientes', 'metodo' => 'POST']);
    $versao = Versao::create(['operacao_id' => $op->id, 'versao' => '1.0']);
    $versao->objetos()->create(['nome' => 'root']);

    return $ws;
}

function urlEntrada(Webservice $ws, string $rota = 'clientes'): string
{
    return rtrim(config('app.url'), '/')."/ws/{$ws->id}/{$rota}";
}

it('aceita sem autenticação e devolve a integração', function () {
    $ws = novaEntrada('nenhuma');

    $this->postJson(urlEntrada($ws), ['a' => 1])
        ->assertOk()
        ->assertJsonPath('status', 'concluida');
});

it('bearer: recusa sem token ou com token errado', function () {
    $ws = novaEntrada('bearer', ['token' => 'segredo']);

    $this->postJson(urlEntrada($ws), [])->assertUnauthorized();
    $this->postJson(urlEntrada($ws), [], ['Authorization' => 'Bearer outro'])->assertUnauthorized();
    $this->postJson(urlEntrada($ws), [], ['Authorization' => 'Bearer segredo'])->assertOk();
});

it('api key: confere o cabeçalho', function () {
    $ws = novaEntrada('apikey', ['cabecalho' => 'X-Chave', 'chave' => 'abc']);

    $this->postJson(urlEntrada($ws), [], ['X-Chave' => 'errada'])->assertUnauthorized();
    $this->postJson(urlEntrada($ws), [], ['X-Chave' => 'abc'])->assertOk();
});

it('basic: confere usuário e senha', function () {
    $ws = novaEntrada('basic', ['usuario' => 'u', 'senha' => 's']);

    $this->postJson(urlEntrada($ws), [], ['Authorization' => 'Basic '.base64_encode('u:errada')])->assertUnauthorized();
    $this->postJson(urlEntrada($ws), [], ['Authorization' => 'Basic '.base64_encode('u:s')])->assertOk();
});

it('oauth2: troca as credenciais por um token e usa o token', function () {
    $ws = novaEntrada('oauth2', ['client_id' => 'cid', 'client_secret' => 'sec']);

    $this->postJson(urlEntrada($ws), [])->assertUnauthorized();
    $this->postJson(urlEntrada($ws, 'oauth/token'), ['client_id' => 'cid', 'client_secret' => 'ruim'])->assertUnauthorized();

    $token = $this->postJson(urlEntrada($ws, 'oauth/token'), ['client_id' => 'cid', 'client_secret' => 'sec'])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->json('access_token');

    $this->postJson(urlEntrada($ws), [], ['Authorization' => "Bearer {$token}"])->assertOk();
    $this->postJson(urlEntrada($ws), [], ['Authorization' => 'Bearer inventado'])->assertUnauthorized();
});

it('responde 404 para rota ou webservice desconhecido', function () {
    $ws = novaEntrada('nenhuma');

    $this->postJson(urlEntrada($ws, 'nao-existe'), [])->assertNotFound();
    $this->postJson(rtrim(config('app.url'), '/').'/ws/999999/clientes', [])->assertNotFound();
});

it('webservice do tipo consumir não recebe chamadas', function () {
    $ws = novaEntrada('nenhuma');
    $ws->update(['tipo' => 'consumir']);

    $this->postJson(urlEntrada($ws), [])->assertNotFound();
});

it('conteúdo inválido vira integração com erro', function () {
    $ws = novaEntrada('nenhuma');

    $this->call('POST', urlEntrada($ws), [], [], [], ['CONTENT_TYPE' => 'application/json'], '{quebrado')
        ->assertStatus(422)
        ->assertJsonPath('status', 'erro');
});
