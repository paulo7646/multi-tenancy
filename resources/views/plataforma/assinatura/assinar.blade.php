@extends('plataforma.assinatura.layout')

@section('titulo', 'Assinar: ' . $assinatura->titulo)

@section('conteudo')
    <h1>{{ $assinatura->titulo }}</h1>
    <p class="suave">{{ $empresa['nome'] }} · Assinante: {{ $assinante->nome }} ({{ $assinante->email }})@if($assinante->papel) · {{ $assinante->papel }}@endif</p>

    @if (session('ok'))
        <div class="alerta ok">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
        <div class="alerta erro">{{ $errors->first() }}</div>
    @endif

    @if ($impedimento)
        <div class="alerta {{ $assinante->status === 'assinou' ? 'ok' : 'erro' }}">{{ $impedimento }}</div>

        @if ($assinatura->status === 'concluido')
            <p><a class="botao" href="{{ url('/verificar/' . $assinatura->codigo_verificacao) }}">Ver certificado e verificar</a></p>
        @endif
    @else
        <iframe src="{{ url('/assinar/' . $token . '/documento') }}" title="Documento"></iframe>
        <p class="suave">Arquivo: {{ $assinatura->versao->nome_original }} · SHA-256 <span class="mono">{{ $assinatura->hash_documento }}</span></p>

        <form method="POST" action="{{ url('/assinar/' . $token) }}">
            @csrf

            @if ($assinatura->exigir_codigo)
                <label for="codigo">Código de 6 dígitos enviado para {{ $assinante->email }}</label>
                <input type="text" id="codigo" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" value="{{ old('codigo') }}">
                <div class="linha">
                    <button type="submit" formaction="{{ url('/assinar/' . $token . '/codigo') }}" class="sec">
                        {{ $assinante->codigo_hash ? 'Enviar outro código' : 'Enviar código por e-mail' }}
                    </button>
                </div>
            @endif

            <label for="nome">Digite o seu nome completo para confirmar</label>
            <input type="text" id="nome" name="nome" value="{{ old('nome') }}" autocomplete="name">

            <label style="font-weight: normal; display: flex; gap: 8px; align-items: flex-start;">
                <input type="checkbox" name="aceite" value="1" required>
                <span>Li o documento e concordo em assiná-lo eletronicamente. Meu IP e horário serão registrados.</span>
            </label>

            <div class="linha">
                <button type="submit">Assinar documento</button>
            </div>
        </form>

        <details style="margin-top: 22px;">
            <summary class="suave">Recusar este documento</summary>
            <form method="POST" action="{{ url('/assinar/' . $token . '/recusar') }}">
                @csrf
                <label for="motivo">Motivo</label>
                <textarea id="motivo" name="motivo" rows="3" required></textarea>
                <div class="linha"><button type="submit" class="perigo">Recusar</button></div>
            </form>
        </details>
    @endif
@endsection
