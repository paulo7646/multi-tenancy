<?php

namespace App\Webservice\Motor;

use App\Webservice\Models\Operacao;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Chama a URL de uma operação (host + rota) com a autenticação, os headers e
 * os parâmetros cadastrados, e devolve o corpo da resposta.
 */
class Cliente
{
    /**
     * @param  array<string, mixed>  $valores  valores dos parâmetros sem valor fixo (nome => valor)
     */
    public static function buscar(Operacao $operacao, array $valores = []): string
    {
        $operacao->loadMissing(['webservice', 'parametros']);
        $webservice = $operacao->webservice;

        if (blank($webservice?->host)) {
            throw new ProcessamentoException('O webservice não tem host cadastrado.');
        }

        $url = $operacao->url();
        $query = $corpo = $headers = [];

        foreach ($operacao->parametros as $parametro) {
            $valor = filled($parametro->valor) ? $parametro->valor : ($valores[$parametro->nome] ?? null);

            if (blank($valor)) {
                if ($parametro->obrigatorio) {
                    throw new ProcessamentoException("O parâmetro \"{$parametro->nome}\" é obrigatório.");
                }

                continue;
            }

            match ($parametro->local) {
                'url' => $url = str_replace('{' . $parametro->nome . '}', rawurlencode((string) $valor), $url),
                'corpo' => $corpo[$parametro->nome] = $valor,
                'header' => $headers[$parametro->nome] = $valor,
                default => $query[$parametro->nome] = $valor,
            };
        }

        $requisicao = static::requisicao($operacao, $headers);

        try {
            $resposta = $requisicao->send($operacao->metodo, $url, array_filter([
                'query' => $query ?: null,
                'json' => $corpo ?: null,
            ]));
        } catch (Throwable $e) {
            throw new ProcessamentoException("Não foi possível chamar {$url}: " . $e->getMessage(), previous: $e);
        }

        if (! $resposta->successful()) {
            throw new ProcessamentoException("{$url} respondeu HTTP {$resposta->status()}: " . mb_substr(trim($resposta->body()), 0, 300));
        }

        return $resposta->body();
    }

    protected static function requisicao(Operacao $operacao, array $headers): PendingRequest
    {
        $webservice = $operacao->webservice;
        $credenciais = $webservice->credenciais ?? [];

        $requisicao = Http::timeout($operacao->timeout ?: 30)
            ->withHeaders([...($webservice->headers ?? []), ...$headers])
            ->accept($operacao->formato === 'xml' ? 'application/xml' : 'application/json');

        return match ($webservice->autenticacao) {
            'basic' => $requisicao->withBasicAuth((string) ($credenciais['usuario'] ?? ''), (string) ($credenciais['senha'] ?? '')),
            'bearer' => $requisicao->withToken((string) ($credenciais['token'] ?? '')),
            'apikey' => $requisicao->withHeaders([(string) ($credenciais['cabecalho'] ?? 'X-API-Key') => (string) ($credenciais['chave'] ?? '')]),
            'oauth2' => $requisicao->withToken(static::tokenOAuth2($webservice->getKey(), $credenciais)),
            default => $requisicao,
        };
    }

    /**
     * Token de client credentials, guardado em cache até perto de expirar.
     */
    protected static function tokenOAuth2(int|string $webservice, array $credenciais): string
    {
        $chave = 'webservice-oauth2-' . (function_exists('tenant') && tenant() ? tenant('id') : 'central') . "-{$webservice}";

        // store central: o cache do cliente usa tags, que o driver de banco não suporta
        return Cache::store('central')->remember($chave, 300, function () use ($credenciais) {
            $resposta = Http::asForm()->post((string) ($credenciais['token_url'] ?? ''), array_filter([
                'grant_type' => 'client_credentials',
                'client_id' => $credenciais['client_id'] ?? null,
                'client_secret' => $credenciais['client_secret'] ?? null,
                'scope' => $credenciais['scope'] ?? null,
            ]));

            if (! $resposta->successful() || blank($resposta->json('access_token'))) {
                throw new ProcessamentoException('Não foi possível obter o token OAuth2 (HTTP ' . $resposta->status() . ').');
            }

            return (string) $resposta->json('access_token');
        });
    }
}
