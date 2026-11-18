{{-- Tela aberta dentro de uma janela (iframe): sem menu lateral nem barra do topo, só o conteúdo. --}}
@if (\App\Sistema\Runtime\Janela::ativa())
    <style>
        .fi-sidebar, .fi-sidebar-close-overlay, .fi-topbar, .fi-topbar-ctn { display: none !important; }
        .fi-main-ctn { margin-inline-start: 0 !important; }
        .fi-main { max-width: none !important; }
    </style>
@endif
