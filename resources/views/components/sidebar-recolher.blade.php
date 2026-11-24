{{--
    Seta de recolher/abrir o menu lateral, dentro do próprio menu (canto direito). No desktop o botão do topo some.
    Menu aberto: seta para a esquerda na borda direita. Menu recolhido: só a seta (para a direita), centralizada. Sempre no meio da altura do menu.
--}}
<style>
    @media (min-width: 1024px) {
        .fi-topbar-collapse-sidebar-btn-ctn { display: none !important; }
    }

    /* o menu se separa do conteúdo: fundo próprio, borda e sombra na lateral */
    .fi-sidebar {
        background-color: light-dark(#ffffff, #18181b) !important;
        border-inline-end: 1px solid light-dark(#d4d4d8, #3f3f46);
        box-shadow: 4px 0 12px -6px rgb(0 0 0 / .25);
    }

    /* recolhido: não aparece nada além da seta */
    .fi-sidebar:not(.fi-sidebar-open) > :not(.jn-recolher) { display: none !important; }

    /* no meio da altura do menu, na borda direita, e maior */
    .jn-recolher { position: absolute; top: 50%; z-index: 2; transform: translateY(-50%); }
    .jn-recolher[data-aberto='true'] { right: .5rem; }
    .jn-recolher[data-aberto='false'] { left: 50%; transform: translate(-50%, -50%); }
</style>

<div
    class="jn-recolher"
    x-data="{}"
    x-bind:data-aberto="$store.sidebar.isOpen ? 'true' : 'false'"
>
    <x-filament::icon-button
        color="gray"
        size="lg"
        icon-size="lg"
        icon="heroicon-m-chevron-left"
        label="Recolher o menu"
        tooltip="Recolher o menu"
        x-show="$store.sidebar.isOpen"
        x-on:click="$store.sidebar.close()"
    />

    <x-filament::icon-button
        color="gray"
        size="lg"
        icon-size="lg"
        icon="heroicon-m-chevron-right"
        label="Abrir o menu"
        tooltip="Abrir o menu"
        x-cloak
        x-show="! $store.sidebar.isOpen"
        x-on:click="$store.sidebar.open()"
    />
</div>
