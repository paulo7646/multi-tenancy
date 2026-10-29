<?php

namespace App\Sistema;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
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

        $codigo = Artisan::call('tenants:migrate', $parametros);
        $saida = trim(Artisan::output());

        if ($codigo !== 0 || str_contains($saida, 'FAIL')) {
            throw new RuntimeException($saida !== '' ? $saida : 'A atualização falhou.');
        }

        return ['aplicadas' => preg_match_all('/\bDONE\b/', $saida), 'saida' => $saida];
    }

    public static function mensagem(array $resultado, bool $todos): string
    {
        $alvo = $todos ? 'dos clientes' : 'do cliente';

        return $resultado['aplicadas'] === 0
            ? "O banco {$alvo} já está atualizado."
            : "{$resultado['aplicadas']} migration(s) aplicada(s) " . ($todos ? 'nos clientes.' : 'no cliente.');
    }
}
