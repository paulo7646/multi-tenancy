<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 70px 36px 55px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1d2330; }
        header { position: fixed; top: -55px; left: 0; right: 0; height: 48px; border-bottom: 1px solid #999; }
        header .logo { float: left; margin-right: 10px; max-height: 40px; }
        header .empresa { font-size: 12px; font-weight: bold; }
        header .dados { font-size: 8px; color: #555; }
        footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8px; color: #666; border-top: 1px solid #ccc; padding-top: 4px; }
        footer .pagina:after { content: counter(page); }
        h1 { font-size: 16px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eceef2; text-align: left; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
        .filtros { font-size: 9px; color: #555; margin-bottom: 6px; }
    </style>
</head>
<body>
    @if ($cabecalho)
        <header>
            @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
            <div class="empresa">{{ $empresa['nome'] }}</div>
            <div class="dados">
                {{ collect([$empresa['documento'], $empresa['endereco'], $empresa['contato']])->filter()->implode(' · ') }}
            </div>
        </header>
    @endif

    <footer>
        {{ $rodape }}
        <div>Emitido em {{ now()->format('d/m/Y H:i') }}@if($emitidoPor) por {{ $emitidoPor }}@endif · Página <span class="pagina"></span></div>
    </footer>

    <main>
        <h1>{{ $titulo }}</h1>
        @yield('conteudo')
    </main>
</body>
</html>
