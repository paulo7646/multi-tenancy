<?php

namespace App\Webservice\Http;

use App\Webservice\Models\Operacao;
use App\Webservice\Models\Webservice;
use App\Webservice\Motor\Integrador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Entrada dos webservices disponibilizados: /ws/{webservice}/{rota}. Um terceiro
 * envia o conteúdo (JSON ou XML) e ele é processado com o layout vigente da
 * operação, gerando uma integração com lotes.
 *
 * Com OAuth2, o terceiro pega o token em POST /ws/{webservice}/oauth/token
 * (client_id e client_secret, client credentials) e o envia como Bearer.
 */
class EntradaController extends Controller
{
    public function __invoke(Request $request, int $webservice, string $rota = ''): JsonResponse
    {
        $ws = Webservice::where('tipo', 'disponibilizar')->where('ativo', true)->find($webservice);

        if (! $ws) {
            return response()->json(['erro' => 'Webservice não encontrado.'], 404);
        }

        if ($ws->autenticacao === 'oauth2' && trim($rota, '/') === 'oauth/token' && $request->isMethod('POST')) {
            return $this->emitirToken($request, $ws);
        }

        if (! $this->autorizado($request, $ws)) {
            return response()->json(['erro' => 'Não autorizado.'], 401, ['WWW-Authenticate' => 'Basic']);
        }

        $operacao = Operacao::where('webservice_id', $ws->getKey())
            ->where('ativo', true)
            ->where('metodo', strtoupper($request->method()))
            ->get()
            ->first(fn (Operacao $o) => trim($o->rota, '/') === trim($rota, '/'));

        if (! $operacao) {
            return response()->json(['erro' => 'Operação não encontrada para este método e rota.'], 404);
        }

        $integracao = Integrador::receber($operacao, (string) $request->getContent());
        $integracao->load('lotes');

        return response()->json([
            'integracao' => $integracao->getKey(),
            'status' => $integracao->status,
            'erro' => $integracao->erro,
            'lotes' => $integracao->lotes->map(fn ($l) => ['numero' => $l->numero, 'status' => $l->status, 'mensagem' => $l->mensagem])->all(),
        ], $integracao->status === 'concluida' ? 200 : ($integracao->status === 'parcial' ? 207 : 422));
    }

    protected function autorizado(Request $request, Webservice $ws): bool
    {
        $credenciais = $ws->credenciais ?? [];

        return match ($ws->autenticacao) {
            'nenhuma' => true,
            'basic' => filled($credenciais['usuario'] ?? null)
                && hash_equals((string) $credenciais['usuario'], (string) $request->getUser())
                && hash_equals((string) ($credenciais['senha'] ?? ''), (string) $request->getPassword()),
            'bearer' => filled($credenciais['token'] ?? null) && hash_equals((string) $credenciais['token'], (string) $request->bearerToken()),
            'apikey' => filled($credenciais['chave'] ?? null)
                && hash_equals((string) $credenciais['chave'], (string) $request->header($credenciais['cabecalho'] ?? 'X-API-Key')),
            'oauth2' => filled($request->bearerToken()) && Cache::store('central')->has(static::chaveDoToken($ws, (string) $request->bearerToken())),
            default => false,
        };
    }

    /**
     * Client credentials: troca client_id e client_secret por um token de 1 hora.
     */
    protected function emitirToken(Request $request, Webservice $ws): JsonResponse
    {
        $credenciais = $ws->credenciais ?? [];

        $ok = $request->input('grant_type', 'client_credentials') === 'client_credentials'
            && filled($credenciais['client_id'] ?? null)
            && hash_equals((string) $credenciais['client_id'], (string) $request->input('client_id'))
            && hash_equals((string) ($credenciais['client_secret'] ?? ''), (string) $request->input('client_secret'));

        if (! $ok) {
            return response()->json(['error' => 'invalid_client'], 401);
        }

        $token = Str::random(48);
        Cache::store('central')->put(static::chaveDoToken($ws, $token), true, 3600);

        return response()->json(['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600]);
    }

    protected static function chaveDoToken(Webservice $ws, string $token): string
    {
        // store central: o cache do cliente usa tags, que o driver de banco não suporta
        return 'webservice-oauth2-entrada-' . (function_exists('tenant') && tenant() ? tenant('id') : 'central') . '-' . $ws->getKey() . '-' . hash('sha256', $token);
    }
}
