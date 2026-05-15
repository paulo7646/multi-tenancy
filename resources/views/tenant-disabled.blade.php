<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Empresa desativada</title>

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-950 via-orange-950 to-slate-900 flex items-center justify-center p-4 antialiased">

    <div class="w-full max-w-md">

        <div
            role="alert"
            class="relative overflow-hidden rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl p-8 sm:p-10 text-center"
        >

            {{-- Glow --}}
            <div class="absolute inset-0 bg-gradient-to-br from-orange-500/10 via-transparent to-transparent pointer-events-none"></div>

            {{-- Icon --}}
            <div class="relative flex justify-center mb-8">
                <div class="flex items-center justify-center w-24 h-24 rounded-full bg-orange-500/20 ring-2 ring-orange-500/30 shadow-lg shadow-orange-900/20">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-12 h-12 text-orange-400"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>

                </div>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mb-4">
                Empresa desativada
            </h1>

            {{-- Description --}}
            <p class="text-slate-300 leading-relaxed text-sm sm:text-base mb-8">
                O acesso a esta empresa está temporariamente
                <span class="text-orange-400 font-medium">desativado</span>.

                <br class="hidden sm:block">

                Entre em contato com o suporte técnico para mais informações.
            </p>

            {{-- Actions --}}
            <div class="space-y-3">

                <a href="mailto:suporte@seudominio.com.br"
                   class="group flex items-center justify-center gap-2 w-full rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-semibold py-3 px-6 transition-all duration-200 shadow-lg hover:shadow-orange-500/30 hover:-translate-y-0.5">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>

                    </svg>

                    Contatar suporte
                </a>

                <a href="{{ url('/client/login') }}"
                   class="flex items-center justify-center gap-2 w-full rounded-xl border border-white/10 bg-white/10 hover:bg-white/15 text-slate-300 hover:text-white font-medium py-3 px-6 transition-all duration-200">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-5 h-5"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/>

                    </svg>

                    Voltar ao login
                </a>

            </div>

        </div>

        {{-- Footer --}}
        <p class="mt-6 text-center text-xs text-slate-500 tracking-wide">
            Código de erro:
            <span class="text-slate-400">TENANT_DISABLED</span>

            &bull;

            {{ now()->format('d/m/Y H:i') }}
        </p>

    </div>

</body>
</html>
