<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-x-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                    <x-heroicon-m-building-office-2 class="h-5 w-5" />
                </div>
                
                <div class="grid flex-1 text-start">
                    <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        Filial Ativa
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Selecione a filial para visualizar os dados
                    </p>
                </div>
            </div>

            <div class="fi-tenant-menu w-full sm:w-auto">
                <x-filament::dropdown placement="bottom-end">
                    <x-slot name="trigger">
                        @php
                            $nomeFilial = $filial_id ? $this->getFiliais()[$filial_id] : 'Todas as filiais';
                            $iniciais = $filial_id ? strtoupper(substr($nomeFilial, 0, 2)) : 'TF';
                        @endphp
                        <button
                            type="button"
                            class="group flex w-full sm:w-auto items-center justify-between sm:justify-start gap-x-3 rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-gray-950/10 transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:bg-gray-900 dark:ring-white/20 dark:hover:bg-white/5 dark:focus-visible:bg-white/5"
                        >
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-gray-100 text-xs font-bold text-gray-950 dark:bg-gray-800 dark:text-white">
                                {{ $iniciais }}
                            </div>

                            <span class="grid justify-items-start text-start">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $filial_id ? 'Filial selecionada' : 'Visão global' }}
                                </span>

                                <span class="text-gray-950 dark:text-white">
                                    {{ $nomeFilial }}
                                </span>
                            </span>

                            <x-filament::icon
                                icon="heroicon-m-chevron-down"
                                class="ms-auto h-5 w-5 shrink-0 text-gray-400 transition duration-75 group-hover:text-gray-500 group-focus-visible:text-gray-500 dark:text-gray-500 dark:group-hover:text-gray-400 dark:group-focus-visible:text-gray-400"
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
        </div>
    </x-filament::section>
</x-filament-widgets::widget>