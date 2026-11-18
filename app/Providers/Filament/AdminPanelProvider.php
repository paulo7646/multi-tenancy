<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Sistema\Pages\ModuloPage;
use App\Plataforma\Plataforma;
use App\Sistema\Enums\Banco;
use App\Sistema\Registro;
use App\Webservice\Resources\AgendamentoResource;
use App\Webservice\Resources\EventoResource;
use App\Webservice\Resources\IntegracaoResource;
use App\Webservice\Resources\ObjetoResource;
use App\Webservice\Resources\OperacaoResource;
use App\Webservice\Resources\VersaoResource;
use App\Webservice\Resources\WebserviceResource;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            // documentos, assinatura eletrônica, impressão e dados da empresa
            ->resources(Plataforma::recursos())
            ->pages(Plataforma::paginas())
            // webservices (consumidos ou disponibilizados) e layouts de integração
            ->resources([
                WebserviceResource::class,
                OperacaoResource::class,
                VersaoResource::class,
                ObjetoResource::class,
                EventoResource::class,
                IntegracaoResource::class,
                AgendamentoResource::class,
            ])
            // telas dos módulos da base pai (dicionário de dados)
            ->resources(Registro::resources(Banco::Central))
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                ModuloPage::class, // visão geral de cada módulo
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
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('components.janela-estilos'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('components.janelas-gerenciador'))
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
