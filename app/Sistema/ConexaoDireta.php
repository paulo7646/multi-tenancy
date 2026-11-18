<?php

namespace App\Sistema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atrás de um PgBouncer em modo transaction, o app conecta no pooler (DB_HOST/DB_PORT), mas
 * o que mexe na estrutura do banco (migrate, tenants:migrate, db:..., criar e apagar o banco
 * de um cliente) precisa de uma conexão direta ao PostgreSQL (DB_DIRECT_HOST/DB_DIRECT_PORT):
 * o pooler pode devolver uma conexão diferente a cada transação e não serve para o que
 * depende de sessão.
 *
 * Sem DB_DIRECT_HOST isto não faz nada. A volta ao pooler é garantida: restaurar() no fim do
 * comando ou do job e, se algo falhar no meio, no encerramento da requisição ou do processo.
 */
class ConexaoDireta
{
    /** Comandos que rodam pela conexão direta (o * vale para qualquer sufixo) */
    public const COMANDOS = ['migrate', 'migrate:*', 'tenants:migrate*', 'tenants:rollback', 'tenants:seed', 'tenants:run', 'db:*', 'schema:*'];

    /** Conexões que apontam para o PostgreSQL (a dos clientes sai do molde tenant_template) */
    protected const CONEXOES = ['central', 'pgsql', 'tenant_template'];

    /** @var array<string, array{host: mixed, port: mixed, url: mixed}>|null o que valia antes de usar() */
    protected static ?array $original = null;

    public static function habilitada(): bool
    {
        return (bool) config('database.direta');
    }

    public static function vale(?string $comando): bool
    {
        return $comando !== null && Str::is(self::COMANDOS, $comando);
    }

    public static function usar(): void
    {
        if (! static::habilitada() || static::$original !== null) {
            return;
        }

        static::$original = [];

        foreach (self::CONEXOES as $nome) {
            static::$original[$nome] = [
                'host' => config("database.connections.{$nome}.host"),
                'port' => config("database.connections.{$nome}.port"),
                'url' => config("database.connections.{$nome}.url"),
            ];

            config([
                "database.connections.{$nome}.host" => config('database.connections.central_direta.host'),
                "database.connections.{$nome}.port" => config('database.connections.central_direta.port'),
                // com DB_URL o Laravel daria prioridade à URL e ignoraria o endereço direto
                "database.connections.{$nome}.url" => null,
            ]);
        }

        static::reconectar();

        // rede de segurança: se o comando ou o job falhar sem chegar no restaurar(), volta no fim
        app()->terminating(fn () => static::restaurar());
    }

    public static function restaurar(): void
    {
        if (static::$original === null) {
            return;
        }

        foreach (static::$original as $nome => $valores) {
            config([
                "database.connections.{$nome}.host" => $valores['host'],
                "database.connections.{$nome}.port" => $valores['port'],
                "database.connections.{$nome}.url" => $valores['url'],
            ]);
        }

        static::$original = null;
        static::reconectar();
    }

    /**
     * Roda o que for pela conexão direta e volta ao pooler mesmo se der erro.
     *
     * @template T
     *
     * @param  callable(): T  $acao
     * @return T
     */
    public static function durante(callable $acao): mixed
    {
        static::usar();

        try {
            return $acao();
        } finally {
            static::restaurar();
        }
    }

    /**
     * Fecha as conexões abertas para as próximas já saírem pelo endereço novo.
     * (Uma transação aberta nelas seria perdida: não chame dentro de transação.)
     */
    protected static function reconectar(): void
    {
        foreach ([...self::CONEXOES, 'tenant'] as $nome) {
            DB::purge($nome);
        }
    }
}
