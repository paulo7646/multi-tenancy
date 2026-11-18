<?php

namespace App\Sistema;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Atualiza o banco dos clientes: roda as migrations de tenant pendentes
 * (php artisan tenants:migrate), de um cliente ou de todos.
 */
class AtualizadorDeClientes
{
    /**
     * @return array{aplicadas: int, saida: string}
     *
     * @throws RuntimeException
     */
    public static function atualizar(?Tenant $tenant = null): array
    {
        set_time_limit(0);

        $parametros = ['--force' => true];

        if ($tenant) {
            $parametros['--tenants'] = [$tenant->getTenantKey()];
        }

        $codigo = ConexaoDireta::durante(fn () => Artisan::call('tenants:migrate', $parametros));
        static::esquecerPendentes($tenant);
        $saida = trim(Artisan::output());

        if ($codigo !== 0 || str_contains($saida, 'FAIL')) {
            throw new RuntimeException($saida !== '' ? $saida : 'A atualização falhou.');
        }

        return ['aplicadas' => preg_match_all('/\bDONE\b/', $saida), 'saida' => $saida];
    }

    /**
     * Quantas migrations de cliente ainda não rodaram no banco deste cliente.
     * Em cache por um minuto (cada consulta abre o banco do cliente).
     */
    public static function pendentes(Tenant $tenant): int
    {
        return Cache::store('central')->remember("tenant-pendentes-{$tenant->getTenantKey()}", 60, function () use ($tenant) {
            $arquivos = collect(glob(database_path('migrations/tenant/*.php')) ?: [])->map(fn (string $f) => basename($f, '.php'));

            $rodadas = $tenant->executar(fn () => Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : collect());

            return $arquivos->diff($rodadas)->count();
        });
    }

    public static function esquecerPendentes(?Tenant $tenant = null): void
    {
        foreach ($tenant ? [$tenant] : Tenant::all() as $t) {
            Cache::store('central')->forget("tenant-pendentes-{$t->getTenantKey()}");
        }
    }

    public static function mensagem(array $resultado, bool $todos): string
    {
        $alvo = $todos ? 'dos clientes' : 'do cliente';

        return $resultado['aplicadas'] === 0
            ? "O banco {$alvo} já está atualizado."
            : "{$resultado['aplicadas']} migration(s) aplicada(s) ".($todos ? 'nos clientes.' : 'no cliente.');
    }
}
