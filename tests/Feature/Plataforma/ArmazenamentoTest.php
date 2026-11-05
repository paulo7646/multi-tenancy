<?php

use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Documentos\DocumentoException;
use App\Plataforma\Models\Arquivo;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function origem(string $nome = 'contrato.txt', string $conteudo = 'conteúdo do contrato'): array
{
    return ['conteudo' => $conteudo, 'nome' => $nome, 'mime' => null];
}

it('cria o documento com a primeira versão e o hash do conteúdo', function () {
    $arquivo = Armazenamento::criar(['nome' => 'Contrato'], origem());

    $versao = $arquivo->versaoAtual;

    expect($arquivo->nome)->toBe('Contrato')
        ->and($versao->numero)->toBe(1)
        ->and($versao->hash)->toBe(hash('sha256', 'conteúdo do contrato'))
        ->and($versao->mime)->toBe('text/plain')
        ->and($versao->integra())->toBeTrue();

    Storage::disk('local')->assertExists($versao->caminho);
});

it('cada versão nova vira a atual e as antigas ficam guardadas', function () {
    $arquivo = Armazenamento::criar([], origem());
    $segunda = Armazenamento::novaVersao($arquivo, origem(conteudo: 'segunda'), 'ajuste');

    $arquivo->refresh();

    expect($segunda->numero)->toBe(2)
        ->and($arquivo->versao_atual_id)->toBe($segunda->id)
        ->and($arquivo->versoes()->count())->toBe(2)
        ->and($arquivo->versoes()->reorder('numero')->first()->conteudo())->toBe('conteúdo do contrato');
});

it('detecta arquivo alterado no disco', function () {
    $versao = Armazenamento::criar([], origem())->versaoAtual;

    Storage::disk('local')->put($versao->caminho, 'adulterado');

    expect($versao->integra())->toBeFalse();
});

it('recusa extensão bloqueada, arquivo vazio e arquivo grande demais', function () {
    expect(fn () => Armazenamento::criar([], origem('virus.exe')))->toThrow(DocumentoException::class, '.exe');
    expect(fn () => Armazenamento::criar([], origem(conteudo: '')))->toThrow(DocumentoException::class, 'vazio');

    config(['plataforma.documentos.max_mb' => 0]);
    expect(fn () => Armazenamento::criar([], origem()))->toThrow(DocumentoException::class, 'limite');
});

it('respeita a cota de armazenamento', function () {
    config(['plataforma.documentos.cota_mb' => 1]);

    Armazenamento::criar([], origem(conteudo: str_repeat('a', 900 * 1024)));

    expect(fn () => Armazenamento::criar([], origem(conteudo: str_repeat('b', 200 * 1024))))
        ->toThrow(DocumentoException::class, 'cota');
});

it('apagar vai para a lixeira e apagar de vez remove os arquivos', function () {
    $arquivo = Armazenamento::criar([], origem());
    $caminho = $arquivo->versaoAtual->caminho;

    $arquivo->delete();
    expect(Arquivo::find($arquivo->id))->toBeNull()
        ->and(Arquivo::withTrashed()->find($arquivo->id))->not->toBeNull();
    Storage::disk('local')->assertExists($caminho);

    Armazenamento::apagarDefinitivamente(Arquivo::withTrashed()->find($arquivo->id));

    expect(Arquivo::withTrashed()->find($arquivo->id))->toBeNull();
    Storage::disk('local')->assertMissing($caminho);
});
