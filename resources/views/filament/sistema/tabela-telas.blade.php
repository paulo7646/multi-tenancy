@php
    $rotulo = $tipo === 'formularios' ? 'formulário' : 'gerenciamento';
@endphp

<div class="flex flex-col gap-3">
    @forelse ($itens as $item)
        <div class="flex items-center justify-between gap-3 rounded-lg px-4 py-3 ring-1 ring-gray-950/10 dark:ring-white/10">
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $item->titulo }}</div>
                <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                    {{ $item->nome_arquivo }}.php · {{ $item->nome_fisico }}
                    @if ($tipo === 'gerenciamentos')
                        · formulário: {{ $item->formulario?->titulo ?? 'nenhum' }}
                    @endif
                </div>
            </div>

            <x-filament::button tag="a" :href="$urlEditar($item)" size="sm" color="gray" icon="heroicon-o-pencil-square">
                Abrir
            </x-filament::button>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum {{ $rotulo }} nesta tabela.</p>
    @endforelse

    @if ($urlNovo)
        <div>
            <x-filament::button tag="a" :href="$urlNovo" size="sm" icon="heroicon-o-plus">
                Novo {{ $rotulo }}
            </x-filament::button>
        </div>
    @endif
</div>
