<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Certificado de assinatura</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
        th { background: #f2f2f2; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9px; word-break: break-all; }
        .topo { margin-bottom: 14px; }
    </style>
</head>
<body>
    <div class="topo">
        <h1>Certificado de assinatura eletrônica</h1>
        <div>{{ $empresa['nome'] }}@if($empresa['documento']) · {{ $empresa['documento'] }}@endif</div>
    </div>

    <h2>Documento</h2>
    <table>
        <tr><th style="width: 28%">Título</th><td>{{ $assinatura->titulo }}</td></tr>
        <tr><th>Arquivo</th><td>{{ $assinatura->versao->nome_original }}</td></tr>
        <tr><th>Hash SHA-256</th><td class="mono">{{ $assinatura->hash_documento }}</td></tr>
        <tr><th>Situação</th><td>{{ \App\Plataforma\Models\Assinatura::STATUS[$assinatura->status] ?? $assinatura->status }}@if($assinatura->concluido_em) em {{ $assinatura->concluido_em->format('d/m/Y H:i:s') }}@endif</td></tr>
        <tr><th>Código de verificação</th><td>{{ $assinatura->codigo_verificacao }}</td></tr>
    </table>

    <h2>Assinantes</h2>
    <table>
        <tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Assinou em</th><th>IP</th></tr>
        @foreach ($assinatura->assinantes as $assinante)
            <tr>
                <td>{{ $assinante->nome }}@if($assinante->identificacao)<br>{{ $assinante->identificacao }}@endif</td>
                <td>{{ $assinante->email }}</td>
                <td>{{ $assinante->papel }}</td>
                <td>{{ $assinante->assinado_em?->format('d/m/Y H:i:s') ?? '—' }}</td>
                <td>{{ $assinante->ip }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Trilha de auditoria</h2>
    <table>
        <tr><th>Quando</th><th>Evento</th><th>IP</th></tr>
        @foreach ($assinatura->eventos as $evento)
            <tr>
                <td>{{ $evento->created_at->format('d/m/Y H:i:s') }}</td>
                <td>{{ $evento->descricao }}</td>
                <td>{{ $evento->ip }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Verificação</h2>
    <table>
        <tr>
            <td style="width: 25%; border: 0;"><img src="{{ $qr }}" width="110" height="110" alt="QR"></td>
            <td style="border: 0;">
                Confira a autenticidade deste documento em:<br>
                <strong>{{ $link }}</strong><br><br>
                O arquivo assinado é o que tem o hash acima. Qualquer alteração no arquivo muda o hash.
            </td>
        </tr>
    </table>
</body>
</html>
