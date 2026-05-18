<div class="fi-topbar-filial-selector">
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            @php
                $filiais = $this->getFiliais();
                $nomeFilial = $filial_id ? ($filiais[$filial_id] ?? 'Filial') : 'Todas as filiais';
                $iniciais = $filial_id ? strtoupper(substr($nomeFilial, 0, 2)) : 'TF';
            @endphp
            <button
                type="button"
                class="group flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition duration-75 hover:bg-gray-100 focus-visible:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
            >
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-400">
                    {{ $iniciais }}
                </div>

                <span class="hidden sm:block max-w-[120px] truncate text-start">
                    {{ $nomeFilial }}
                </span>

                <x-filament::icon
                    icon="heroicon-m-chevron-down"
                    class="h-4 w-4 shrink-0 text-gray-400 transition duration-75 group-hover:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400"
                />
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            <x-filament::dropdown.list.item
                wire:click="$set('filial_id', '')"
                tag="button"
                icon="heroicon-m-globe-alt"
                color="{{ !$filial_id ? 'primary' : 'gray' }}"
            >
                <div class="flex items-center justify-between w-full gap-4">
                    <span>Todas as filiais</span>
                    @if (!$filial_id)
                        <x-heroicon-m-check class="h-4 w-4" />
                    @endif
                </div>
            </x-filament::dropdown.list.item>

            @foreach ($this->getFiliais() as $id => $nome)
                <x-filament::dropdown.list.item
                    wire:click="$set('filial_id', '{{ $id }}')"
                    tag="button"
                    icon="heroicon-m-building-office"
                    color="{{ $filial_id == $id ? 'primary' : 'gray' }}"
                >
                    <div class="flex items-center justify-between w-full gap-4">
                        <span>{{ $nome }}</span>
                        @if ($filial_id == $id)
                            <x-heroicon-m-check class="h-4 w-4" />
                        @endif
                    </div>
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
