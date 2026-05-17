<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Filial Ativa
        </x-slot>

        <div class="flex items-center gap-4">
            <div class="flex-1">
                <select
                    wire:model.live="filial_id"
                    class="fi-select-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500"
                >
                    <option value="">Todas as filiais</option>
                    @foreach ($this->getFiliais() as $id => $nome)
                        <option value="{{ $id }}" @selected($filial_id == $id)>{{ $nome }}</option>
                    @endforeach
                </select>
            </div>

            @if ($filial_id)
                <x-filament::badge color="success">
                    Filtrando por filial
                </x-filament::badge>
            @else
                <x-filament::badge color="gray">
                    Todas as filiais
                </x-filament::badge>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
