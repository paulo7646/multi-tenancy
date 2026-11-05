<?php

use App\Plataforma\Assinatura\AssinaturaException;
use App\Plataforma\Assinatura\Assinaturas;
use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Mail\NotificacaoAssinatura;
use App\Plataforma\Models\Assinante;
use App\Plataforma\Models\Assinatura;
use App\Plataforma\Models\EventoAssinatura;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
});

function pdfDeTeste(): array
{
    return ['conteudo' => Pdf::loadHTML('<p>Contrato de teste</p>')->output(), 'nome' => 'contrato.pdf', 'mime' => 'application/pdf'];
}

/** Pedido com dois assinantes, em ordem. */
function novoPedido(array $dados = [], ?array $assinantes = null): Assinatura
{
    $versao = Armazenamento::criar(['nome' => 'Contrato'], pdfDeTeste())->versaoAtual;

    return Assinaturas::criar(['titulo' => 'Contrato de teste', ...$dados], $versao, $assinantes ?? [
        ['nome' => 'Ana Souza', 'email' => 'ana@teste.com', 'papel' => 'Contratante'],
        ['nome' => 'Bruno Lima', 'email' => 'bruno@teste.com', 'papel' => 'Contratado'],
    ]);
}

/** Link enviado ao assinante (o token só existe no e-mail). */
function linkDe(string $email): string
{
    $link = null;

    Mail::assertSent(NotificacaoAssinatura::class, function (NotificacaoAssinatura $mail) use ($email, &$link) {
        if ($mail->hasTo($email) && isset($mail->dados['link']) && str_contains($mail->visao, 'convite')) {
            $link = $mail->dados['link'];
        }

        return true;
    });

    return $link;
}

function codigoDe(string $email): string
{
    $codigo = null;

    Mail::assertSent(NotificacaoAssinatura::class, function (NotificacaoAssinatura $mail) use ($email, &$codigo) {
        if ($mail->hasTo($email) && isset($mail->dados['codigo'])) {
            $codigo = $mail->dados['codigo'];
        }

        return true;
    });

    return $codigo;
}

function assinarComo(string $email, bool $comCodigo = true): void
{
    $token = basename(linkDe($email));
    $assinante = Assinaturas::porToken($token);

    if ($comCodigo) {
        Assinaturas::enviarCodigo($assinante);
        $codigo = codigoDe($email);
    }

    Assinaturas::assinar($assinante->refresh(), $codigo ?? null, ['ip' => '10.0.0.1', 'user_agent' => 'Teste']);
}

it('só aceita PDF', function () {
    $versao = Armazenamento::criar([], ['conteudo' => 'texto', 'nome' => 'a.txt'])->versaoAtual;

    Assinaturas::criar(['titulo' => 'X'], $versao, [['nome' => 'A', 'email' => 'a@a.com']]);
})->throws(AssinaturaException::class, 'PDF');

it('exige pelo menos um assinante', function () {
    $versao = Armazenamento::criar([], pdfDeTeste())->versaoAtual;

    Assinaturas::criar(['titulo' => 'X'], $versao, []);
})->throws(AssinaturaException::class, 'assinante');

it('cria o pedido em rascunho, com código de verificação e hash do arquivo', function () {
    $pedido = novoPedido();

    expect($pedido->status)->toBe('rascunho')
        ->and($pedido->codigo_verificacao)->toMatch('/^[A-Z0-9]{10}-[A-Z0-9]{10}$/')
        ->and($pedido->hash_documento)->toBe($pedido->versao->hash)
        ->and($pedido->assinantes)->toHaveCount(2)
        ->and($pedido->eventos)->toHaveCount(1);

    Mail::assertNothingSent();
});

it('na ordem obrigatória, convida só o primeiro e depois o segundo', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    Mail::assertSent(NotificacaoAssinatura::class, fn ($m) => $m->hasTo('ana@teste.com'));
    Mail::assertNotSent(NotificacaoAssinatura::class, fn ($m) => $m->hasTo('bruno@teste.com'));

    assinarComo('ana@teste.com');

    Mail::assertSent(NotificacaoAssinatura::class, fn ($m) => $m->hasTo('bruno@teste.com') && str_contains($m->visao, 'convite'));
});

it('sem ordem obrigatória, convida todos de uma vez', function () {
    Assinaturas::enviar(novoPedido(['ordem_obrigatoria' => false]));

    Mail::assertSent(NotificacaoAssinatura::class, fn ($m) => $m->hasTo('ana@teste.com'));
    Mail::assertSent(NotificacaoAssinatura::class, fn ($m) => $m->hasTo('bruno@teste.com'));
});

it('o segundo não assina antes do primeiro', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    $bruno = $pedido->assinantes()->where('email', 'bruno@teste.com')->first();

    expect(Assinaturas::impedimento($bruno))->toContain('Aguarde');
});

it('assina com código, conclui, gera o certificado e a verificação confere', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    assinarComo('ana@teste.com');
    expect($pedido->refresh()->status)->toBe('em_andamento');

    assinarComo('bruno@teste.com');
    $pedido->refresh();

    expect($pedido->status)->toBe('concluido')
        ->and($pedido->concluido_em)->not->toBeNull()
        ->and($pedido->certificado)->not->toBeNull()
        ->and($pedido->certificado->versaoAtual->mime)->toBe('application/pdf')
        ->and($pedido->assinantes->pluck('status')->unique()->all())->toBe(['assinou'])
        ->and($pedido->assinantes->first()->ip)->toBe('10.0.0.1')
        ->and($pedido->assinantes->first()->hash_no_ato)->toBe($pedido->hash_documento);

    $resultado = Assinaturas::verificar($pedido->codigo_verificacao);

    expect($resultado['arquivo_integro'])->toBeTrue()
        ->and($resultado['trilha_integra'])->toBeTrue();
});

it('recusa código errado e bloqueia depois de muitas tentativas', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    $ana = Assinaturas::porToken(basename(linkDe('ana@teste.com')));
    Assinaturas::enviarCodigo($ana);
    $certo = codigoDe('ana@teste.com');
    $errado = $certo === '000000' ? '111111' : '000000';

    for ($i = 0; $i < 5; $i++) {
        expect(fn () => Assinaturas::assinar($ana->refresh(), $errado))->toThrow(AssinaturaException::class, 'incorreto');
    }

    expect(fn () => Assinaturas::assinar($ana->refresh(), $certo))->toThrow(AssinaturaException::class, 'tentativas');
});

it('assinar sem pedir o código quando o pedido não exige', function () {
    $pedido = novoPedido(['exigir_codigo' => false], [['nome' => 'Ana Souza', 'email' => 'ana@teste.com']]);
    Assinaturas::enviar($pedido);

    assinarComo('ana@teste.com', comCodigo: false);

    expect($pedido->refresh()->status)->toBe('concluido');
});

it('não assina se o arquivo foi alterado depois do envio', function () {
    $pedido = novoPedido(['exigir_codigo' => false], [['nome' => 'Ana Souza', 'email' => 'ana@teste.com']]);
    Assinaturas::enviar($pedido);

    Storage::disk('local')->put($pedido->versao->caminho, 'outro conteúdo');

    expect(fn () => assinarComo('ana@teste.com', comCodigo: false))->toThrow(AssinaturaException::class, 'não confere');
});

it('a recusa cancela o pedido', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    Assinaturas::recusar(Assinaturas::porToken(basename(linkDe('ana@teste.com'))), 'Valores errados');

    expect($pedido->refresh()->status)->toBe('cancelado')
        ->and($pedido->motivo_cancelamento)->toContain('Valores errados')
        ->and($pedido->assinantes->first()->status)->toBe('recusou');
});

it('pedido cancelado ou expirado não pode ser assinado', function () {
    $pedido = novoPedido(['expira_em' => now()->addDay()]);
    Assinaturas::enviar($pedido);
    $ana = Assinaturas::porToken(basename(linkDe('ana@teste.com')));

    $pedido->update(['expira_em' => now()->subMinute()]);
    expect(Assinaturas::impedimento($ana->refresh()))->toContain('prazo');

    $pedido->update(['expira_em' => null]);
    Assinaturas::cancelar($pedido->refresh(), 'Desistência');
    expect(Assinaturas::impedimento($ana->refresh()))->toContain('cancelado');
});

it('um link novo invalida o anterior', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);
    $ana = $pedido->assinantes()->where('email', 'ana@teste.com')->first();

    $primeiro = basename(linkDe('ana@teste.com'));
    Assinaturas::convidar($ana);

    expect(Assinaturas::porToken($primeiro))->toBeNull()
        ->and(Assinaturas::porToken('inventado'))->toBeNull();
});

it('a verificação acusa trilha adulterada', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    EventoAssinatura::where('tipo', 'enviada')->update(['descricao' => 'qualquer coisa']);
    expect(Assinaturas::trilhaIntegra($pedido->refresh()))->toBeTrue(); // a descrição não entra no hash

    EventoAssinatura::where('tipo', 'criada')->update(['dados' => json_encode(['adulterado' => true])]);

    expect(Assinaturas::trilhaIntegra($pedido->refresh()))->toBeFalse();
});

it('código de verificação inexistente não acha nada', function () {
    expect(Assinaturas::verificar('NADA-NADA'))->toBeNull();
});

it('o assinante nunca expõe hashes na serialização', function () {
    $pedido = novoPedido();
    Assinaturas::enviar($pedido);

    $ana = $pedido->assinantes()->where('email', 'ana@teste.com')->first();

    expect($ana->toArray())->not->toHaveKeys(['token_hash', 'codigo_hash']);
});
