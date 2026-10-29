<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckTenantStatus;
use App\Http\Middleware\CheckUserLicense;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use CrescentPurchasing\FilamentAuditing\FilamentAuditingPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Sistema\Enums\Banco;
use App\Sistema\Registro;
use App\Filament\Sistema\Pages\ModuloPage;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/client/theme.css')
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\\Filament\\Client\\Resources')
            // webservices (consumidos ou disponibilizados) e layouts de integração
            ->resources([
                \App\Webservice\Resources\WebserviceResource::class,
                \App\Webservice\Resources\OperacaoResource::class,
                \App\Webservice\Resources\VersaoResource::class,
                \App\Webservice\Resources\ObjetoResource::class,
                \App\Webservice\Resources\EventoResource::class,
                \App\Webservice\Resources\IntegracaoResource::class,
                \App\Webservice\Resources\AgendamentoResource::class,
            ])
            // telas dos módulos dos clientes (dicionário de dados)
            ->resources(Registro::resources(Banco::Tenant))
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\\Filament\\Client\\Pages')
            ->pages([
                Dashboard::class,
                ModuloPage::class, // visão geral de cada módulo
            ])
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\\Filament\\Client\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                FilamentAuditingPlugin::make()
                    ->navigationGroup('Configurações'),
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
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,
                CheckTenantStatus::class,
            ], isPersistent: TRUE)
            ->authMiddleware([
                Authenticate::class,
                CheckUserLicense::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn() => view('components.license-monitor-hook'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn() => view('components.filial-selector-topbar-hook'),
            );
    }
}
