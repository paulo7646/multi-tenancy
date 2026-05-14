<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso não autorizado</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-red-950 to-slate-900 flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-10 text-center shadow-2xl">

            <div class="flex justify-center mb-8">
                <div class="w-24 h-24 bg-red-500/20 rounded-full flex items-center justify-center ring-2 ring-red-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
            </div>

            <h1 class="text-2xl font-bold text-white mb-3">Acesso não autorizado</h1>

            <p class="text-slate-300 text-base leading-relaxed mb-8">
                Sua licença de acesso está inativa ou expirada.<br>
                Entre em contato com o suporte técnico para regularizar sua situação.
            </p>

            <div class="space-y-3">
                <a href="mailto:suporte@seudominio.com.br"
                   class="flex items-center justify-center gap-2 w-full bg-red-600 hover:bg-red-500 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 shadow-lg hover:shadow-red-500/25">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                    </svg>
                    Contatar Suporte
                </a>

                <a href="{{ url('/client/login') }}"
                   class="flex items-center justify-center gap-2 w-full bg-white/10 hover:bg-white/15 text-slate-300 hover:text-white font-medium py-3 px-6 rounded-xl transition-all duration-200 border border-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                    </svg>
                    Voltar ao Login
                </a>
            </div>

        </div>

        <p class="text-center text-slate-500 text-sm mt-6">
            Código de erro: LICENSE_INACTIVE &bull; {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>
</body>
</html>
