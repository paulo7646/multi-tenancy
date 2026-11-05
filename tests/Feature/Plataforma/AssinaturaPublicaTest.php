<?php

use App\Plataforma\Assinatura\Assinaturas;
use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Mail\NotificacaoAssinatura;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
});

function pedidoPublico(array $dados = []): array
{
    $versao = Armazenamento::criar([], ['conteudo' => Pdf::loadHTML('<p>Contrato</p>')->output(), 'nome' => 'contrato.pdf'])->versaoAtual;
    $pedido = Assinaturas::criar(['titulo' => 'Contrato público', ...$dados], $versao, [['nome' => 'Ana Souza', 'email' => 'ana@teste.com']]);
    Assinaturas::enviar($pedido);

    $token = null;
    Mail::assertSent(NotificacaoAssinatura::class, function ($mail) use (&$token) {
        $token = isset($mail->dados['link']) ? basename($mail->dados['link']) : $token;

        return true;
    });

    return [$pedido, $token];
}

function base(): string
{
    return rtrim(config('app.url'), '/');
}

it('mostra a página do assinante e o PDF do documento', function () {
    [, $token] = pedidoPublico();

    $this->get(base()."/assinar/{$token}")
        ->assertOk()
        ->assertSee('Contrato público')
        ->assertSee('Ana Souza');

    $this->get(base()."/assinar/{$token}/documento")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('link inválido responde 404', function () {
    $this->get(base().'/assinar/inventado')->assertNotFound();
    $this->get(base().'/assinar/inventado/documento')->assertNotFound();
});

it('assina pelo navegador: código, nome e aceite', function () {
    [$pedido, $token] = pedidoPublico();

    $this->post(base()."/assinar/{$token}/codigo")->assertRedirect();

    $codigo = null;
    Mail::assertSent(NotificacaoAssinatura::class, function ($mail) use (&$codigo) {
        $codigo = $mail->dados['codigo'] ?? $codigo;

        return true;
    });

    // nome errado
    $this->post(base()."/assinar/{$token}", ['aceite' => 1, 'nome' => 'Outra Pessoa', 'codigo' => $codigo])
        ->assertSessionHasErrors('nome');

    // sem aceite
    $this->post(base()."/assinar/{$token}", ['nome' => 'Ana Souza', 'codigo' => $codigo])
        ->assertSessionHasErrors('aceite');

    // código errado
    $this->post(base()."/assinar/{$token}", ['aceite' => 1, 'nome' => 'Ana Souza', 'codigo' => $codigo === '000000' ? '111111' : '000000'])
        ->assertSessionHasErrors('assinatura');

    // tudo certo (o nome vale sem acento e sem diferença de maiúsculas)
    $this->post(base()."/assinar/{$token}", ['aceite' => 1, 'nome' => 'ana souza', 'codigo' => $codigo])
        ->assertRedirect("/assinar/{$token}")
        ->assertSessionHas('ok');

    $pedido->refresh();

    expect($pedido->status)->toBe('concluido')
        ->and($pedido->assinantes->first()->ip)->not->toBeNull();

    $this->get(base()."/assinar/{$token}")->assertOk()->assertSee('já assinou');
});

it('o assinante pode recusar', function () {
    [$pedido, $token] = pedidoPublico();

    $this->post(base()."/assinar/{$token}/recusar", ['motivo' => 'Não concordo'])->assertRedirect();

    expect($pedido->refresh()->status)->toBe('cancelado');
});

it('a verificação pública mostra o resultado de um certificado', function () {
    [$pedido, $token] = pedidoPublico(['exigir_codigo' => false]);

    $this->post(base()."/assinar/{$token}", ['aceite' => 1, 'nome' => 'Ana Souza']);

    $this->get(base()."/verificar/{$pedido->refresh()->codigo_verificacao}")
        ->assertOk()
        ->assertSee('assinado por todos e íntegro');

    $this->get(base().'/verificar/NADA-NADA')->assertOk()->assertSee('Nenhum documento encontrado');
    $this->get(base().'/verificar')->assertOk();
});

it('a verificação acusa um arquivo alterado', function () {
    [$pedido, $token] = pedidoPublico(['exigir_codigo' => false]);
    $this->post(base()."/assinar/{$token}", ['aceite' => 1, 'nome' => 'Ana Souza']);

    Storage::disk('local')->put($pedido->versao->caminho, 'adulterado');

    $this->get(base()."/verificar/{$pedido->refresh()->codigo_verificacao}")
        ->assertOk()
        ->assertSee('não confere com o hash');
});

it('escapa o conteúdo digitado pelo usuário nas páginas públicas', function () {
    $versao = Armazenamento::criar([], ['conteudo' => Pdf::loadHTML('<p>x</p>')->output(), 'nome' => 'a.pdf'])->versaoAtual;
    $pedido = Assinaturas::criar(['titulo' => '<script>alert(1)</script>'], $versao, [['nome' => 'A', 'email' => 'a@a.com']]);
    Assinaturas::enviar($pedido);

    $token = null;
    Mail::assertSent(NotificacaoAssinatura::class, function ($mail) use (&$token) {
        $token = basename($mail->dados['link'] ?? '') ?: $token;

        return true;
    });

    $this->get(base()."/assinar/{$token}")->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});
