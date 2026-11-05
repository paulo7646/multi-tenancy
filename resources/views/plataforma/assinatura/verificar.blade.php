@extends('plataforma.assinatura.layout')

@section('titulo', 'Verificação de assinatura')

@section('conteudo')
    <h1>Verificação de assinatura</h1>

    @if (! $resultado)
        <div class="alerta erro">Nenhum documento encontrado com este código.</div>
        <form method="GET" onsubmit="event.preventDefault(); window.location = '{{ url('/verificar') }}/' + encodeURIComponent(document.getElementById('c').value.trim());">
            <label for="c">Código de verificação</label>
            <input type="text" id="c" placeholder="XXXXXXXXXX-XXXXXXXXXX">
            <div class="linha"><button type="submit">Verificar</button></div>
        </form>
    @else
        @php($a = $resultado['assinatura'])
        <p class="suave">{{ $empresa['nome'] }} · Código {{ $a->codigo_verificacao }}</p>

        <div class="alerta {{ $a->status === 'concluido' && $resultado['arquivo_integro'] && $resultado['trilha_integra'] ? 'ok' : 'erro' }}">
            @if ($a->status === 'concluido' && $resultado['arquivo_integro'] && $resultado['trilha_integra'])
                Documento assinado por todos e íntegro.
            @else
                Situação: {{ \App\Plataforma\Models\Assinatura::STATUS[$a->status] ?? $a->status }}.
                @unless ($resultado['arquivo_integro']) O arquivo guardado não confere com o hash do envio. @endunless
                @unless ($resultado['trilha_integra']) A trilha de auditoria foi alterada. @endunless
            @endif
        </div>

        <table>
            <tr><th>Documento</th><td>{{ $a->titulo }}</td></tr>
            <tr><th>Arquivo</th><td>{{ $a->versao->nome_original }}</td></tr>
            <tr><th>SHA-256</th><td class="mono">{{ $a->hash_documento }}</td></tr>
            <tr><th>Arquivo íntegro</th><td>{{ $resultado['arquivo_integro'] ? 'Sim' : 'Não' }}</td></tr>
            <tr><th>Trilha íntegra</th><td>{{ $resultado['trilha_integra'] ? 'Sim' : 'Não' }}</td></tr>
        </table>

        <h2 style="font-size: 16px;">Assinantes</h2>
        <table>
            <tr><th>Nome</th><th>Papel</th><th>Situação</th><th>Quando</th></tr>
            @foreach ($a->assinantes as $s)
                <tr>
                    <td>{{ $s->nome }}</td>
                    <td>{{ $s->papel }}</td>
                    <td>{{ \App\Plataforma\Models\Assinante::STATUS[$s->status] ?? $s->status }}</td>
                    <td>{{ $s->assinado_em?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
