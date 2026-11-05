<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo', 'Assinatura')</title>
    <style>
        :root { color-scheme: light dark; --bg: #f4f5f7; --card: #fff; --texto: #1d2330; --suave: #5b6578; --borda: #d9dde5; --primaria: #b45309; --ok: #15803d; --erro: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #14171f; --card: #1d212c; --texto: #e8ebf2; --suave: #9aa3b5; --borda: #343a49; --primaria: #f59e0b; --ok: #4ade80; --erro: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 16px; background: var(--bg); color: var(--texto); font: 16px/1.5 system-ui, sans-serif; }
        main { max-width: 720px; margin: 0 auto; background: var(--card); border: 1px solid var(--borda); border-radius: 10px; padding: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .suave { color: var(--suave); font-size: 14px; }
        .alerta { padding: 10px 14px; border-radius: 8px; border: 1px solid var(--borda); margin: 14px 0; }
        .alerta.erro { border-color: var(--erro); color: var(--erro); }
        .alerta.ok { border-color: var(--ok); color: var(--ok); }
        label { display: block; margin: 14px 0 4px; font-weight: 600; }
        input[type=text], input[type=email], textarea { width: 100%; padding: 10px; border: 1px solid var(--borda); border-radius: 8px; background: transparent; color: inherit; font: inherit; }
        button, .botao { display: inline-block; padding: 10px 18px; border: 0; border-radius: 8px; background: var(--primaria); color: #fff; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        button.sec { background: transparent; color: var(--texto); border: 1px solid var(--borda); }
        button.perigo { background: var(--erro); }
        iframe { width: 100%; height: 480px; border: 1px solid var(--borda); border-radius: 8px; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 14px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--borda); }
        .mono { font-family: ui-monospace, monospace; font-size: 12px; word-break: break-all; }
        .linha { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
    </style>
</head>
<body>
    <main>
        @yield('conteudo')
    </main>
</body>
</html>
