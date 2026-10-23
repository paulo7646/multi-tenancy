<?php

namespace App\Providers;

use App\Models\UserLicense;
use App\Sistema\Base\BaseResource;
use App\Navigation\CustomNavigationManager;
use App\Observers\UserLicenseObserver;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use CrescentPurchasing\FilamentAuditing\Filament\Resources\Audits\AuditResource;
use Filament\Navigation\NavigationManager;
use Filament\Pages\BasePage as Page;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Menu lateral configurável pela tela "Navegação"
        $this->app->extend(NavigationManager::class, fn () => new CustomNavigationManager);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        UserLicense::observe(UserLicenseObserver::class);

        $this->preserveShieldV3PermissionKeys();
    }

    /**
     * Mantém o padrão de nomes de permissão do Shield 3 (ex.: view_any_tenant),
     * que é o usado pelas policies e pelas roles já gravadas no banco.
     */
    protected function preserveShieldV3PermissionKeys(): void
    {
        // Resources de pacotes cujo nome de permissão no v3 não segue a regra geral.
        $legacySubjects = [
            RoleResource::class => 'role',
            AuditResource::class => 'crescent::purchasing::filament::auditing::filament::audit',
        ];

        FilamentShield::buildPermissionKeyUsing(
            function (string $entity, ?string $affix, string $subject, string $case, string $separator) use ($legacySubjects) {
                return match (true) {
                    // telas do dicionário: view_any_op_produto, create_op_produto...
                    is_subclass_of($entity, BaseResource::class) => Str::snake($affix) . '_' . $entity::sujeitoPermissao(),
                    is_subclass_of($entity, Resource::class) => Str::snake($affix) . '_' . ($legacySubjects[$entity] ?? Str::of($entity)
                        ->afterLast('Resources\\')
                        ->before('Resource')
                        ->replace('\\', '')
                        ->snake()
                        ->replace('_', '::')
                        ->toString()),
                    is_subclass_of($entity, Page::class) => 'page_' . class_basename($entity),
                    is_subclass_of($entity, Widget::class) => 'widget_' . class_basename($entity),
                    default => null,
                };
            }
        );
    }
}
