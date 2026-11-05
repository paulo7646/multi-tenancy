<?php

namespace App\Plataforma\Assinatura;

use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Mail\NotificacaoAssinatura;
use App\Plataforma\Models\Assinante;
use App\Plataforma\Models\Assinatura;
use App\Plataforma\Models\Configuracao;
use App\Plataforma\Models\EventoAssinatura;
use App\Plataforma\Models\Versao;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Assinatura eletrônica simples: o assinante abre um link único (token), confirma
 * um código recebido por e-mail e assina. Cada passo entra numa trilha de
 * auditoria encadeada por hash; ao fim sai um certificado em PDF com QR de
 * verificação.
 */
class Assinaturas
{
    /**
     * Cria o pedido (rascunho) com os assinantes. O documento tem que ser um PDF.
     *
     * @param  array{titulo: string, ordem_obrigatoria?: bool, exigir_codigo?: bool, expira_em?: mixed, tabela?: string|null, registro_id?: int|null}  $dados
     * @param  list<array{nome: string, email: string, identificacao?: string|null, papel?: string|null, ordem?: int, user_id?: int|null}>  $assinantes
     *
     * @throws AssinaturaException
     */
    public static function criar(array $dados, Versao $versao, array $assinantes): Assinatura
    {
        if ($versao->mime !== 'application/pdf') {
            throw new AssinaturaException('Só é possível assinar documentos em PDF.');
        }

        if ($assinantes === []) {
            throw new AssinaturaException('Informe pelo menos um assinante.');
        }

        if (! $versao->integra()) {
            throw new AssinaturaException('O arquivo do documento foi alterado ou não existe mais.');
        }

        return DB::transaction(function () use ($dados, $versao, $assinantes) {
            $usuario = auth()->user();

            $assinatura = Assinatura::create([
                ...$dados,
                'versao_id' => $versao->getKey(),
                'hash_documento' => $versao->hash,
                'codigo_verificacao' => strtoupper(Str::random(10)).'-'.strtoupper(Str::random(10)),
                'user_id' => $usuario?->getAuthIdentifier(),
                'criado_por' => $usuario?->name,
            ]);

            foreach (array_values($assinantes) as $i => $assinante) {
                $assinatura->assinantes()->create([...$assinante, 'ordem' => $assinante['ordem'] ?? $i + 1]);
            }

            static::registrar($assinatura, 'criada', 'Pedido de assinatura criado.');

            return $assinatura->refresh();
        });
    }

    /**
     * Abre o pedido: convida quem assina primeiro (ou todos, se a ordem não for obrigatória).
     *
     * @throws AssinaturaException
     */
    public static function enviar(Assinatura $assinatura): void
    {
        if ($assinatura->status !== 'rascunho') {
            throw new AssinaturaException('Só um rascunho pode ser enviado.');
        }

        $assinatura->update(['status' => 'em_andamento']);
        static::registrar($assinatura, 'enviada', 'Pedido enviado para assinatura.');
        static::convidarProximos($assinatura);
    }

    /**
     * Gera um link novo (o anterior deixa de valer) e manda o convite por e-mail.
     * Devolve o link.
     */
    public static function convidar(Assinante $assinante): string
    {
        $token = Str::random(48);
        $dias = (int) config('plataforma.assinatura.validade_dias', 7);

        $assinante->update([
            'token_hash' => hash('sha256', $token),
            'token_expira' => now()->addDays($dias),
            'notificado_em' => now(),
        ]);

        $link = url("/assinar/{$token}");
        $assinatura = $assinante->documento;

        Mail::to($assinante->email)->send(new NotificacaoAssinatura(
            "Assinatura solicitada: {$assinatura->titulo}",
            'plataforma.mail.convite-assinatura',
            ['assinante' => $assinante, 'assinatura' => $assinatura, 'link' => $link, 'empresa' => Configuracao::empresa()],
        ));

        static::registrar($assinatura, 'convite', "Convite enviado para {$assinante->email}.", $assinante);

        return $link;
    }

    /**
     * O assinante que abriu este link, se ele ainda vale.
     */
    public static function porToken(string $token): ?Assinante
    {
        $assinante = Assinante::with('documento')->where('token_hash', hash('sha256', $token))->first();

        if (! $assinante || $assinante->token_expira?->isPast()) {
            return null;
        }

        return $assinante;
    }

    /**
     * Motivo pelo qual este assinante não pode assinar agora (null = pode).
     */
    public static function impedimento(Assinante $assinante): ?string
    {
        $assinatura = $assinante->documento;

        return match (true) {
            $assinante->status === 'assinou' => 'Você já assinou este documento.',
            $assinante->status === 'recusou' => 'Você recusou este documento.',
            $assinatura->status === 'cancelado' => 'Este pedido de assinatura foi cancelado.',
            $assinatura->status === 'concluido' => 'Este documento já foi assinado por todos.',
            $assinatura->status === 'rascunho' => 'Este pedido ainda não foi enviado.',
            $assinatura->expirou() => 'O prazo para assinar este documento terminou.',
            $assinatura->ordem_obrigatoria && static::pendentesAntes($assinante) > 0 => 'Aguarde: outras pessoas precisam assinar antes de você.',
            default => null,
        };
    }

    /**
     * Manda o código de 6 dígitos para o e-mail do assinante.
     *
     * @throws AssinaturaException
     */
    public static function enviarCodigo(Assinante $assinante): void
    {
        if ($mensagem = static::impedimento($assinante)) {
            throw new AssinaturaException($mensagem);
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutos = (int) config('plataforma.assinatura.codigo_minutos', 15);

        $assinante->update([
            'codigo_hash' => static::hashDoCodigo($assinante, $codigo),
            'codigo_expira' => now()->addMinutes($minutos),
            'tentativas' => 0,
        ]);

        Mail::to($assinante->email)->send(new NotificacaoAssinatura(
            'Seu código para assinar',
            'plataforma.mail.codigo-assinatura',
            ['assinante' => $assinante, 'assinatura' => $assinante->documento, 'codigo' => $codigo, 'minutos' => $minutos],
        ));

        static::registrar($assinante->documento, 'codigo', 'Código de confirmação enviado.', $assinante);
    }

    /**
     * Registra a assinatura. Quando é a última, conclui o pedido e gera o certificado.
     *
     * @param  array{ip?: string|null, user_agent?: string|null}  $contexto
     *
     * @throws AssinaturaException
     */
    public static function assinar(Assinante $assinante, ?string $codigo, array $contexto = []): void
    {
        if ($mensagem = static::impedimento($assinante)) {
            throw new AssinaturaException($mensagem);
        }

        $assinatura = $assinante->documento;

        if ($assinatura->exigir_codigo) {
            static::conferirCodigo($assinante, (string) $codigo, $contexto);
        }

        // o arquivo precisa ser o mesmo que foi enviado para assinatura
        if (! $assinatura->versao->integra() || $assinatura->versao->hash !== $assinatura->hash_documento) {
            throw new AssinaturaException('O arquivo do documento não confere com o enviado para assinatura.');
        }

        DB::transaction(function () use ($assinante, $assinatura, $contexto) {
            $assinante->update([
                'status' => 'assinou',
                'assinado_em' => now(),
                'ip' => $contexto['ip'] ?? null,
                'user_agent' => $contexto['user_agent'] ?? null,
                'hash_no_ato' => $assinatura->hash_documento,
                'codigo_hash' => null,
            ]);

            static::registrar($assinatura, 'assinatura', "{$assinante->nome} assinou o documento.", $assinante, $contexto, ['hash_documento' => $assinatura->hash_documento]);

            $pendentes = $assinatura->assinantes()->where('status', '!=', 'assinou')->count();
            $pendentes === 0 ? static::concluir($assinatura) : static::convidarProximos($assinatura);
        });
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $contexto
     *
     * @throws AssinaturaException
     */
    public static function recusar(Assinante $assinante, string $motivo, array $contexto = []): void
    {
        if ($mensagem = static::impedimento($assinante)) {
            throw new AssinaturaException($mensagem);
        }

        $assinatura = $assinante->documento;

        DB::transaction(function () use ($assinante, $assinatura, $motivo, $contexto) {
            $assinante->update(['status' => 'recusou', 'motivo_recusa' => $motivo, 'ip' => $contexto['ip'] ?? null, 'user_agent' => $contexto['user_agent'] ?? null]);
            $assinatura->update(['status' => 'cancelado', 'cancelado_em' => now(), 'motivo_cancelamento' => "Recusado por {$assinante->nome}: {$motivo}"]);

            static::registrar($assinatura, 'recusa', "{$assinante->nome} recusou o documento.", $assinante, $contexto, ['motivo' => $motivo]);
        });
    }

    /**
     * @throws AssinaturaException
     */
    public static function cancelar(Assinatura $assinatura, string $motivo): void
    {
        if (in_array($assinatura->status, ['concluido', 'cancelado'], true)) {
            throw new AssinaturaException('Este pedido já foi encerrado.');
        }

        $assinatura->update(['status' => 'cancelado', 'cancelado_em' => now(), 'motivo_cancelamento' => $motivo]);
        static::registrar($assinatura, 'cancelada', 'Pedido cancelado.', null, [], ['motivo' => $motivo]);
    }

    /**
     * Confere um código de verificação: situação do pedido, assinantes, integridade
     * do arquivo e da trilha.
     *
     * @return array{assinatura: Assinatura, arquivo_integro: bool, trilha_integra: bool}|null
     */
    public static function verificar(string $codigo): ?array
    {
        $assinatura = Assinatura::with(['assinantes', 'versao', 'eventos'])->where('codigo_verificacao', strtoupper(trim($codigo)))->first();

        if (! $assinatura) {
            return null;
        }

        return [
            'assinatura' => $assinatura,
            'arquivo_integro' => $assinatura->versao->integra() && $assinatura->versao->hash === $assinatura->hash_documento,
            'trilha_integra' => static::trilhaIntegra($assinatura),
        ];
    }

    public static function trilhaIntegra(Assinatura $assinatura): bool
    {
        $anterior = null;

        foreach ($assinatura->eventos()->get() as $evento) {
            $esperado = EventoAssinatura::calcularHash($anterior, $assinatura->getKey(), $evento->tipo, $evento->created_at->format('Y-m-d H:i:s'), $evento->dados);

            if ($evento->hash_anterior !== $anterior || ! hash_equals($evento->hash, $esperado)) {
                return false;
            }

            $anterior = $evento->hash;
        }

        return true;
    }

    // ------------------------------------------------------------------

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $contexto
     */
    public static function registrar(Assinatura $assinatura, string $tipo, string $descricao, ?Assinante $assinante = null, array $contexto = [], ?array $dados = null): EventoAssinatura
    {
        // a relação já ordena por id crescente: o último evento vem direto da tabela
        $anterior = EventoAssinatura::where('documento_id', $assinatura->getKey())->latest('id')->value('hash');
        $momento = now();

        return EventoAssinatura::create([
            'documento_id' => $assinatura->getKey(),
            'assinante_id' => $assinante?->getKey(),
            'tipo' => $tipo,
            'descricao' => $descricao,
            'ip' => $contexto['ip'] ?? null,
            'user_agent' => $contexto['user_agent'] ?? null,
            'dados' => $dados,
            'hash_anterior' => $anterior,
            'hash' => EventoAssinatura::calcularHash($anterior, $assinatura->getKey(), $tipo, $momento->format('Y-m-d H:i:s'), $dados),
            'created_at' => $momento,
        ]);
    }

    protected static function pendentesAntes(Assinante $assinante): int
    {
        return $assinante->documento->assinantes()
            ->where('ordem', '<', $assinante->ordem)
            ->where('status', '!=', 'assinou')
            ->count();
    }

    protected static function convidarProximos(Assinatura $assinatura): void
    {
        $pendentes = $assinatura->assinantes()->where('status', 'pendente')->get();

        if ($pendentes->isEmpty()) {
            return;
        }

        $alvo = $assinatura->ordem_obrigatoria ? $pendentes->where('ordem', $pendentes->min('ordem')) : $pendentes;

        foreach ($alvo->whereNull('notificado_em') as $assinante) {
            static::convidar($assinante);
        }
    }

    /**
     * @param  array{ip?: string|null, user_agent?: string|null}  $contexto
     *
     * @throws AssinaturaException
     */
    protected static function conferirCodigo(Assinante $assinante, string $codigo, array $contexto): void
    {
        $limite = (int) config('plataforma.assinatura.codigo_tentativas', 5);

        if ($assinante->codigo_hash === null || $assinante->codigo_expira?->isPast()) {
            throw new AssinaturaException('Peça um código novo: o anterior venceu ou não foi enviado.');
        }

        if ($assinante->tentativas >= $limite) {
            throw new AssinaturaException('Muitas tentativas erradas. Peça um código novo.');
        }

        if (! hash_equals($assinante->codigo_hash, static::hashDoCodigo($assinante, trim($codigo)))) {
            $assinante->increment('tentativas');
            static::registrar($assinante->documento, 'codigo_invalido', 'Código de confirmação incorreto.', $assinante, $contexto);

            throw new AssinaturaException('Código incorreto.');
        }
    }

    protected static function hashDoCodigo(Assinante $assinante, string $codigo): string
    {
        return hash_hmac('sha256', "{$assinante->getKey()}|{$codigo}", (string) config('app.key'));
    }

    /**
     * Todos assinaram: fecha o pedido e gera o certificado (PDF com os dados e o QR de verificação).
     */
    protected static function concluir(Assinatura $assinatura): void
    {
        $assinatura->update(['status' => 'concluido', 'concluido_em' => now()]);
        static::registrar($assinatura->refresh(), 'concluida', 'Todos os assinantes assinaram.');

        $certificado = Armazenamento::criar(
            ['nome' => "Certificado de assinatura - {$assinatura->titulo}.pdf", 'pasta_id' => $assinatura->versao->arquivo?->pasta_id],
            ['conteudo' => static::certificadoPdf($assinatura->refresh()), 'nome' => "certificado-{$assinatura->codigo_verificacao}.pdf", 'mime' => 'application/pdf'],
        );

        $assinatura->update(['certificado_id' => $certificado->getKey()]);

        foreach ($assinatura->assinantes as $assinante) {
            Mail::to($assinante->email)->send(new NotificacaoAssinatura(
                "Documento assinado: {$assinatura->titulo}",
                'plataforma.mail.assinatura-concluida',
                ['assinante' => $assinante, 'assinatura' => $assinatura, 'link' => url("/verificar/{$assinatura->codigo_verificacao}")],
            ));
        }
    }

    public static function certificadoPdf(Assinatura $assinatura): string
    {
        $assinatura->loadMissing(['assinantes', 'eventos', 'versao']);
        $link = url("/verificar/{$assinatura->codigo_verificacao}");

        $qr = (new Writer(new ImageRenderer(new RendererStyle(180, 0), new SvgImageBackEnd)))->writeString($link);

        return Pdf::loadView('plataforma.assinatura.certificado', [
            'assinatura' => $assinatura,
            'empresa' => Configuracao::empresa(),
            'link' => $link,
            'qr' => 'data:image/svg+xml;base64,'.base64_encode($qr),
        ])->setPaper('a4')->output();
    }
}
