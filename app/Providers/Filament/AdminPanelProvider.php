<?php

namespace App\Providers\Filament;

use App\Sistema\Enums\Banco;
use App\Sistema\Registro;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->domains(config('tenancy.central_domains'))
            ->login()
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            // telas dos módulos da base pai (dicionário de dados)
            ->resources(Registro::resources(Banco::Central))
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([
                'universal',
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            // janelas (iframe com X e −, barra inferior): o estilo esconde o menu dentro da janela
            ->renderHook(PanelsRenderHook::SIDEBAR_START, fn () => view('components.sidebar-recolher'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('components.menu-pesquisa'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('components.janela-estilos'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('components.janelas-gerenciador'))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
