<?php

namespace App\Plataforma\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * E-mail do fluxo de assinatura (convite, código, conclusão). Só texto simples e um link.
 */
class NotificacaoAssinatura extends Mailable
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function __construct(public string $assunto, public string $visao, public array $dados = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: $this->visao, with: $this->dados);
    }
}
