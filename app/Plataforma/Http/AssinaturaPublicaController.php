<?php

namespace App\Plataforma\Http;

use App\Plataforma\Assinatura\AssinaturaException;
use App\Plataforma\Assinatura\Assinaturas;
use App\Plataforma\Models\Assinante;
use App\Plataforma\Models\Configuracao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Páginas públicas da assinatura: o assinante entra pelo link com token (sem
 * login) e qualquer pessoa pode verificar um certificado pelo código.
 */
class AssinaturaPublicaController
{
    public function mostrar(string $token): View
    {
        $assinante = $this->assinante($token);
        $assinatura = $assinante->documento->load('versao');

        return view('plataforma.assinatura.assinar', [
            'token' => $token,
            'assinante' => $assinante,
            'assinatura' => $assinatura,
            'impedimento' => Assinaturas::impedimento($assinante),
            'empresa' => Configuracao::empresa(),
        ]);
    }

    /**
     * O PDF, para o quadro da página (só para quem tem o link).
     */
    public function documento(string $token): Response
    {
        $assinante = $this->assinante($token);
        $conteudo = $assinante->documento->versao->conteudo();

        abort_if($conteudo === null, 404);

        return response($conteudo, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function enviarCodigo(string $token): RedirectResponse
    {
        $assinante = $this->assinante($token);

        try {
            Assinaturas::enviarCodigo($assinante);
        } catch (AssinaturaException $e) {
            return back()->withErrors(['assinatura' => $e->getMessage()]);
        }

        return back()->with('ok', "Enviamos um código para {$assinante->email}.");
    }

    public function assinar(Request $request, string $token): RedirectResponse
    {
        $assinante = $this->assinante($token);

        $dados = $request->validate([
            'aceite' => ['accepted'],
            'nome' => ['required', 'string'],
            'codigo' => ['nullable', 'string', 'max:10'],
        ], [
            'aceite.accepted' => 'Marque a confirmação para assinar.',
            'nome.required' => 'Digite o seu nome completo.',
        ]);

        if ($this->normalizar($dados['nome']) !== $this->normalizar($assinante->nome)) {
            return back()->withInput()->withErrors(['nome' => 'O nome digitado não confere com o do assinante.']);
        }

        try {
            Assinaturas::assinar($assinante, $dados['codigo'] ?? null, $this->contexto($request));
        } catch (AssinaturaException $e) {
            return back()->withInput()->withErrors(['assinatura' => $e->getMessage()]);
        }

        return redirect("/assinar/{$token}")->with('ok', 'Documento assinado. Obrigado!');
    }

    public function recusar(Request $request, string $token): RedirectResponse
    {
        $assinante = $this->assinante($token);
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']]);

        try {
            Assinaturas::recusar($assinante, $dados['motivo'], $this->contexto($request));
        } catch (AssinaturaException $e) {
            return back()->withErrors(['assinatura' => $e->getMessage()]);
        }

        return redirect("/assinar/{$token}")->with('ok', 'Você recusou o documento.');
    }

    public function verificar(?string $codigo = null): View
    {
        return view('plataforma.assinatura.verificar', [
            'resultado' => $codigo ? Assinaturas::verificar($codigo) : null,
            'empresa' => Configuracao::empresa(),
        ]);
    }

    protected function assinante(string $token): Assinante
    {
        return Assinaturas::porToken($token) ?? abort(404, 'Link inválido ou vencido.');
    }

    /**
     * @return array{ip: string|null, user_agent: string|null}
     */
    protected function contexto(Request $request): array
    {
        return ['ip' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 500, '')];
    }

    protected function normalizar(string $nome): string
    {
        return (string) Str::of(Str::ascii($nome))->lower()->replaceMatches('/\s+/', ' ')->trim();
    }
}
