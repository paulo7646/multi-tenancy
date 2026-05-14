<div wire:poll.300000ms="checkLicense">
    @if($showBanner)
    <div
        class="fixed top-0 left-0 right-0 z-[9999] bg-amber-500 text-amber-950 px-4 py-3 shadow-lg"
        role="alert"
    >
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <p class="font-semibold text-sm">
                    ⚠️ Sua licença foi desativada. Você será desconectado automaticamente em
                    <strong>{{ $minutesRemaining }} {{ $minutesRemaining === 1 ? 'minuto' : 'minutos' }}</strong>.
                    Entre em contato com o suporte técnico.
                </p>
            </div>
            <a
                href="mailto:suporte@seudominio.com.br"
                class="flex-shrink-0 bg-amber-900/20 hover:bg-amber-900/30 text-amber-950 font-semibold text-xs py-1.5 px-3 rounded-lg transition-colors"
            >
                Contatar Suporte
            </a>
        </div>
    </div>
    @endif
</div>
