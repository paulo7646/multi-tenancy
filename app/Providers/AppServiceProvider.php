<?php

namespace App\Providers;

use App\Models\UserLicense;
use App\Navigation\CustomNavigationManager;
use App\Observers\UserLicenseObserver;
use App\Sistema\Base\BaseResource;
use App\Sistema\ConexaoDireta;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use CrescentPurchasing\FilamentAuditing\Filament\Resources\Audits\AuditResource;
use Filament\Navigation\NavigationManager;
use Filament\Pages\BasePage as Page;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

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

        $this->desfazerTransacaoAbertaAoFinal();
        $this->usarConexaoDiretaNasMigrations();

        // entrada dos webservices disponibilizados: limite por IP e webservice
        RateLimiter::for('webservice', fn (Request $request) => Limit::perMinute((int) config('webservice.limite_por_minuto', 120))
            ->by($request->ip().'|'.$request->route('webservice')));

        $this->preserveShieldV3PermissionKeys();
    }

    /**
     * Atrás de um PgBouncer (DB_DIRECT_HOST), migrate e afins rodam direto no PostgreSQL e
     * voltam para o pooler quando o comando termina (vale também para Artisan::call numa requisição).
     */
    protected function usarConexaoDiretaNasMigrations(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $evento) {
            if (ConexaoDireta::vale($evento->command)) {
                ConexaoDireta::usar();
            }
        });

        Event::listen(CommandFinished::class, function (CommandFinished $evento) {
            if (ConexaoDireta::vale($evento->command)) {
                ConexaoDireta::restaurar();
            }
        });
    }

    /**
     * Com conexão persistente, uma transação esquecida aberta (erro fatal no meio dela) iria para a
     * próxima requisição que pegasse a mesma conexão. No fim de cada requisição, desfaz o que sobrou.
     */
    protected function desfazerTransacaoAbertaAoFinal(): void
    {
        // só faz sentido com conexão persistente; nos testes a transação aberta é a do próprio teste
        if ($this->app->runningUnitTests() || ! config('database.persistente')) {
            return;
        }

        $this->app->terminating(function () {
            foreach (DB::getConnections() as $conexao) {
                if ($conexao->transactionLevel() === 0) {
                    continue;
                }

                try {
                    $conexao->rollBack(0);
                } catch (Throwable $e) {
                    // a conexão caiu no meio: fecha, para o PHP não devolver um PDO ainda em transação
                    report($e);
                    $conexao->disconnect();
                }
            }
        });
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
                    is_subclass_of($entity, BaseResource::class) => Str::snake($affix).'_'.$entity::sujeitoPermissao(),
                    is_subclass_of($entity, Resource::class) => Str::snake($affix).'_'.($legacySubjects[$entity] ?? Str::of($entity)
                        ->afterLast('Resources\\')
                        ->before('Resource')
                        ->replace('\\', '')
                        ->snake()
                        ->replace('_', '::')
                        ->toString()),
                    is_subclass_of($entity, Page::class) => 'page_'.class_basename($entity),
                    is_subclass_of($entity, Widget::class) => 'widget_'.class_basename($entity),
                    default => null,
                };
            }
        );
    }
}
