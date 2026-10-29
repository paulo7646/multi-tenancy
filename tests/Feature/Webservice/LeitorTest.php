<?php

use App\Webservice\Motor\Leitor;
use App\Webservice\Motor\ProcessamentoException;

it('lê JSON', function () {
    expect(Leitor::paraArray('{"clientes":[{"codigo":1}]}', 'json'))
        ->toBe(['clientes' => [['codigo' => 1]]]);
});

it('recusa JSON inválido', function () {
    Leitor::paraArray('{quebrado', 'json');
})->throws(ProcessamentoException::class, 'JSON válido');

it('recusa JSON que não é objeto nem lista', function () {
    Leitor::paraArray('"texto"', 'json');
})->throws(ProcessamentoException::class);

it('lê XML como o conteúdo da raiz', function () {
    $dados = Leitor::paraArray('<dados><clientes><codigo>30</codigo></clientes><clientes><codigo>31</codigo></clientes></dados>', 'xml');

    expect($dados['clientes'])->toHaveCount(2)
        ->and($dados['clientes'][0]['codigo'])->toBe('30');
});

it('recusa XML inválido', function () {
    Leitor::paraArray('<dados><aberto></dados>', 'xml');
})->throws(ProcessamentoException::class, 'XML válido');

it('não busca entidades externas no XML', function () {
    $xml = '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/hostname">]><d><v>&x;</v></d>';

    $dados = Leitor::paraArray($xml, 'xml');

    expect($dados['v'] ?? '')->not->toContain('localhost');
});
