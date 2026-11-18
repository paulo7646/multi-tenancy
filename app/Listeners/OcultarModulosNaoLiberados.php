<?php

namespace App\Listeners;

use App\Sistema\Enums\Banco;
use App\Sistema\Registro;
use App\Sistema\Runtime\Acesso;
use Stancl\Tenancy\Events\TenancyInitialized;
use Throwable;

/**
 * Dentro de uma empresa, a tela de papéis (Shield) só lista as permissões das telas dos módulos
 * que ela tem: as telas dos demais módulos entram na lista de exclusão do Shield.
 */
class OcultarModulosNaoLiberados
{
    /** A lista de exclusão original do Shield, guardada uma vez: cada empresa recalcula a partir dela. */
    protected static ?array $base = null;

    /**
     * Ao sair da empresa (ou entrar em outra no mesmo processo: fila, tenants:run) a lista volta ao original,
     * senão as exclusões de uma empresa passariam para a próxima.
     */
    public function restaurar(): void
    {
        if (static::$base !== null) {
            config(['filament-shield.resources.exclude' => static::$base]);
        }
    }

    public function handle(TenancyInitialized $evento): void
    {
        static::$base ??= (array) config('filament-shield.resources.exclude', []);

        try {
            $ocultas = collect(Registro::resources(Banco::Tenant))
                ->reject(fn (string $resource) => Acesso::liberado($resource::md()->tabela->modulo))
                ->values()
                ->all();
        } catch (Throwable) {
            return; // dicionário ainda não instalado ou banco fora do ar: o Shield lista tudo
        }

        config(['filament-shield.resources.exclude' => [...static::$base, ...$ocultas]]);
    }
}
