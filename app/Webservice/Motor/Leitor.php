<?php

namespace App\Webservice\Motor;

/**
 * Converte o JSON ou XML do retorno em array (o conteúdo da raiz, o {} principal).
 */
class Leitor
{
    public static function paraArray(string $conteudo, string $formato): array
    {
        return $formato === 'xml' ? static::xml($conteudo) : static::json($conteudo);
    }

    protected static function json(string $conteudo): array
    {
        $dados = json_decode($conteudo, true);

        if (! is_array($dados)) {
            throw new ProcessamentoException('O retorno não é um JSON válido: ' . json_last_error_msg() . '.');
        }

        return $dados;
    }

    protected static function xml(string $conteudo): array
    {
        $anterior = libxml_use_internal_errors(true);

        // LIBXML_NONET: nada de buscar entidades externas na rede
        $xml = simplexml_load_string($conteudo, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        $erros = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if ($xml === false) {
            throw new ProcessamentoException('O retorno não é um XML válido' . ($erros ? ': ' . trim($erros[0]->message) : '.'));
        }

        return json_decode(json_encode($xml), true) ?? [];
    }
}
