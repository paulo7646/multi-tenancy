<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Acesso não autorizado</title>

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-950 via-red-950 to-slate-900 flex items-center justify-center p-4 antialiased">

    <div class="w-full max-w-md">

        <div
            role="alert"
            class="relative overflow-hidden rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl p-8 sm:p-10 text-center"
        >

            {{-- Glow --}}
            <div class="absolute inset-0 bg-gradient-to-br from-red-500/10 via-transparent to-transparent pointer-events-none"></div>

            {{-- Icon --}}
            <div class="relative flex justify-center mb-8">
                <div class="flex items-center justify-center w-24 h-24 rounded-full bg-red-500/20 ring-2 ring-red-500/30 shadow-lg shadow-red-900/20">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="w-12 h-12 text-red-400"
                         fill="none"
                         viewBox="0 0 24 24"
                         stroke-width="1.5"
                         stroke="currentColor">

                        <path stroke-linecap="round"
                              stroke-linejoin="round"
                              d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>

                </div>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white mb-4">
                Acesso não autorizado
            </h1>

            {{-- Description --}}
            <p class="text-slate-300 leading-relaxed text-sm sm:text-base mb-8">
                Sua licença de acesso está
                <span class="text-red-400 font-medium">inativa ou expirada</span>.

                <br class="hidden sm:block">

                Entre em contato com o suporte técnico para regularizar sua situação.
            </p>

            {{-- Actions --}}
            <div class="space-y-3">

                @if ($requester)
                    <form method="POST" action="{{ route('license.request') }}">
                        @csrf
                        <button type="submit"
                                class="group flex items-center justify-center gap-2 w-full rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold py-3 px-6 transition-all duration-200 shadow-lg hover:shadow-amber-500/30 hover:-translate-y-0.5">

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"
                                 fill="none"
                                 viewBox="0 0 24 24"
                                 stroke-width="1.5"
                                 stroke="currentColor">

                                <path stroke-linecap="round"
                                      stroke-linejoin="round"
                                      d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/>
                            </svg>

                            Solicitar licença
                        </button>
                    </form>
                @endif

                <a href="mailto:suporte@seudominio.com.br"
                   class="group flex items-center justify-center gap-2 w-full rounded-xl bg-red-600 hover:bg-red-500 text-white font-semibold py-3 px-6 transition-all duration-200 shadow-lg hover:shadow-red-500/30 hover:-translate-y-0.5">

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

                <a href="{{ url('/admin/login') }}"
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
            <span class="text-slate-400">LICENSE_INACTIVE</span>

            &bull;

            {{ now()->format('d/m/Y H:i') }}
        </p>

    </div>

    @if (session('license_request'))
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
        <script>
            const licenseAlerts = {
                created: {
                    icon: 'success',
                    title: 'Solicitação enviada',
                    text: 'Sua solicitação de licença foi enviada para a administração do sistema. Aguarde a liberação.',
                },
                exists: {
                    icon: 'info',
                    title: 'Solicitação já registrada',
                    text: 'Já existe uma licença ou solicitação cadastrada para o seu usuário. Aguarde a liberação pela administração do sistema.',
                },
                limit: {
                    icon: 'warning',
                    title: 'Limite de licenças excedido',
                    text: 'Sua solicitação foi registrada, mas a quantidade de licenças da empresa já foi excedida. A empresa deve entrar em contato com a administração do sistema.',
                },
                expired: {
                    icon: 'error',
                    title: 'Sessão expirada',
                    text: 'Faça login novamente para solicitar a licença.',
                },
            };

            Swal.fire({
                ...licenseAlerts[@json(session('license_request'))],
                confirmButtonText: 'OK',
                confirmButtonColor: '#f59e0b',
                background: '#0f172a',
                color: '#e2e8f0',
            });
        </script>
    @endif

</body>
</html>