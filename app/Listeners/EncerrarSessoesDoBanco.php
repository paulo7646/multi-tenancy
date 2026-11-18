<?php

namespace App\Listeners;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Events\DeletingDatabase;
use Throwable;

/**
 * Antes de apagar o banco de um cliente, encerra as sessões que ainda estão nele. O PostgreSQL
 * não apaga um banco em uso, e conexão persistente ou o pool do PgBouncer deixam sessões
 * abertas depois da requisição.
 *
 * Cada sessão é encerrada separadamente: uma que não pode ser encerrada (por exemplo, a de um
 * superusuário conectado) não impede as outras, e o que sobrar é avisado no log.
 */
class EncerrarSessoesDoBanco
{
    /** Quantas vezes (de 100 ms) esperar as sessões saírem: o PostgreSQL as encerra de forma assíncrona */
    protected const ESPERA = 30;

    /**
     * @return int quantas sessões foram encerradas
     */
    public function handle(DeletingDatabase $evento): int
    {
        $banco = $evento->tenant->database()->getName();

        // conexão ao servidor (direta, se houver pooler), nunca ao banco que vai ser apagado
        $conexao = DB::connection(config('database.direta') ? 'central_direta' : 'central');

        $encerradas = 0;
        $recusadas = [];

        foreach ($conexao->select('select pid from pg_stat_activity where datname = ? and pid <> pg_backend_pid()', [$banco]) as $sessao) {
            try {
                $conexao->select('select pg_terminate_backend(?)', [$sessao->pid]);
                $encerradas++;
            } catch (Throwable $e) {
                $recusadas[] = $sessao->pid;
            }
        }

        for ($tentativa = 0; $tentativa < self::ESPERA && $this->sessoesAbertas($conexao, $banco) > 0; $tentativa++) {
            usleep(100_000);
        }

        if (($restantes = $this->sessoesAbertas($conexao, $banco)) > 0) {
            Log::warning("Ainda há {$restantes} sessão(ões) no banco {$banco} antes de apagá-lo; o DROP DATABASE pode falhar.", ['recusadas' => $recusadas]);
        }

        return $encerradas;
    }

    protected function sessoesAbertas(ConnectionInterface $conexao, string $banco): int
    {
        return (int) $conexao->selectOne(
            'select count(*) as total from pg_stat_activity where datname = ? and pid <> pg_backend_pid()',
            [$banco],
        )->total;
    }
}
