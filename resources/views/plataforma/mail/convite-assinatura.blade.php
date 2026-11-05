<p>Olá, {{ $assinante->nome }}.</p>

<p>{{ $empresa['nome'] }} pediu a sua assinatura no documento <strong>{{ $assinatura->titulo }}</strong>@if($assinante->papel) como <strong>{{ $assinante->papel }}</strong>@endif.</p>

<p><a href="{{ $link }}">Abrir e assinar o documento</a></p>

<p>O link é pessoal e vale até {{ $assinante->token_expira?->format('d/m/Y H:i') }}. Se você não esperava este pedido, ignore este e-mail.</p>
