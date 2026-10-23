<x-filament-panels::page>
    @php
        $gerenciamentos = $this->gerenciamentos();
    @endphp

    @if ($gerenciamentos->isEmpty())
        <x-filament::section>
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon icon="heroicon-o-squares-2x2" class="h-10 w-10 text-gray-400" />
                <p class="text-sm font-medium text-gray-950 dark:text-white">Este módulo ainda não tem telas.</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Cadastre tabelas e gerenciamentos no Dicionário de dados.</p>

                @if ($url = $this->urlDicionario())
                    <x-filament::button tag="a" :href="$url" size="sm" class="mt-2">Abrir o dicionário</x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($gerenciamentos as $gerenciamento)
                <a
                    href="{{ $this->urlGerenciamento($gerenciamento) }}"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-xs ring-1 ring-gray-950/5 transition hover:bg-gray-50 hover:ring-primary-500/40 dark:bg-gray-900 dark:ring-white/10 dark:hover:bg-white/5"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-primary-600 dark:bg-white/5 dark:text-primary-400"
                          @if ($gerenciamento->tabela->cor) style="color: {{ \App\Sistema\Enums\Cor::css($gerenciamento->tabela->cor) }}" @endif>
                        <x-filament::icon :icon="$gerenciamento->icone ?: ($gerenciamento->tabela->icone ?: 'heroicon-o-table-cells')" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $gerenciamento->titulo }}</span>
                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $gerenciamento->tabela->nome }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
