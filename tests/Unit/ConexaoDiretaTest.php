<?php

use App\Listeners\EncerrarSessoesDoBanco;
use App\Models\Tenant;
use App\Sistema\ConexaoDireta;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\DeletingDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

// sem RefreshDatabase: desconectar a conexão padrão apagaria a transação do próprio teste
uses(TestCase::class);

function habilitarConexaoDireta(): void
{
    config([
        'database.direta' => true,
        'database.connections.central_direta.host' => 'pg-direto.exemplo',
        'database.connections.central_direta.port' => '5432',
        'database.connections.central.host' => 'pgbouncer.exemplo',
        'database.connections.central.port' => '6432',
        'database.connections.pgsql.host' => 'pgbouncer.exemplo',
        'database.connections.pgsql.port' => '6432',
        'database.connections.tenant_template.host' => 'pgbouncer.exemplo',
        'database.connections.tenant_template.port' => '6432',
    ]);
}

afterEach(fn () => ConexaoDireta::restaurar());

it('só migrate e comandos de estrutura rodam pela conexão direta', function (string $comando, bool $vale) {
    expect(ConexaoDireta::vale($comando))->toBe($vale);
})->with([
    ['migrate', true],
    ['migrate:fresh', true],
    ['migrate:rollback', true],
    ['tenants:migrate', true],
    ['tenants:migrate-fresh', true],
    ['tenants:run', true],
    ['tenants:rollback', true],
    ['db:seed', true],
    ['queue:work', false],
    ['schedule:run', false],
    ['webservice:agendamentos', false],
    ['tenants:list', false],
]);

it('usar troca host e porta das três conexões e restaurar volta ao pooler', function () {
    habilitarConexaoDireta();

    ConexaoDireta::usar();

    foreach (['central', 'pgsql', 'tenant_template'] as $nome) {
        expect(config("database.connections.{$nome}.host"))->toBe('pg-direto.exemplo')
            ->and((string) config("database.connections.{$nome}.port"))->toBe('5432');
    }

    ConexaoDireta::restaurar();

    foreach (['central', 'pgsql', 'tenant_template'] as $nome) {
        expect(config("database.connections.{$nome}.host"))->toBe('pgbouncer.exemplo')
            ->and((string) config("database.connections.{$nome}.port"))->toBe('6432');
    }
});

it('sem DB_DIRECT_HOST não muda nada', function () {
    habilitarConexaoDireta();
    config(['database.direta' => false]);

    ConexaoDireta::usar();

    expect(config('database.connections.central.host'))->toBe('pgbouncer.exemplo');
});

it('o comando migrate usa a conexão direta e volta ao terminar', function () {
    habilitarConexaoDireta();

    Event::dispatch(new CommandStarting('migrate', new ArrayInput([]), new NullOutput));
    expect(config('database.connections.pgsql.host'))->toBe('pg-direto.exemplo');

    Event::dispatch(new CommandFinished('migrate', new ArrayInput([]), new NullOutput, 0));
    expect(config('database.connections.pgsql.host'))->toBe('pgbouncer.exemplo');
});

it('outros comandos continuam pelo pooler', function () {
    habilitarConexaoDireta();

    Event::dispatch(new CommandStarting('queue:work', new ArrayInput([]), new NullOutput));

    expect(config('database.connections.pgsql.host'))->toBe('pgbouncer.exemplo');
});

it('antes de apagar o banco do cliente, encerra as sessões abertas nele', function () {
    $nome = 'tenant_zzsessoes';
    $servidor = DB::connection('central');
    $servidor->statement("drop database if exists \"{$nome}\"");
    $servidor->statement("create database \"{$nome}\"");

    try {
        $config = config('database.connections.central');
        $sessao = new PDO("pgsql:host={$config['host']};port={$config['port']};dbname={$nome}", $config['username'], $config['password']);
        $sessao->query('select 1');

        $abertas = fn () => (int) $servidor->selectOne('select count(*) c from pg_stat_activity where datname = ?', [$nome])->c;
        expect($abertas())->toBe(1);

        (new EncerrarSessoesDoBanco)->handle(new DeletingDatabase(new Tenant(['id' => 'zzsessoes'])));

        expect($abertas())->toBe(0);

        // sem sessões, o banco pode ser apagado (era o que falhava com conexão persistente)
        $servidor->statement("drop database \"{$nome}\"");
        expect($servidor->selectOne('select count(*) c from pg_database where datname = ?', [$nome])->c)->toBe(0);
    } finally {
        $servidor->statement("drop database if exists \"{$nome}\"");
    }
});

it('com DB_URL a troca ainda vale: a url é tirada enquanto a conexão direta está em uso', function () {
    habilitarConexaoDireta();
    config(['database.connections.central.url' => 'pgsql://u:p@pgbouncer.exemplo:6432/base']);

    ConexaoDireta::usar();
    expect(config('database.connections.central.url'))->toBeNull();

    ConexaoDireta::restaurar();
    expect(config('database.connections.central.url'))->toBe('pgsql://u:p@pgbouncer.exemplo:6432/base');
});

it('durante() volta ao pooler mesmo quando a ação lança exceção', function () {
    habilitarConexaoDireta();

    expect(fn () => ConexaoDireta::durante(function () {
        expect(config('database.connections.pgsql.host'))->toBe('pg-direto.exemplo');

        throw new RuntimeException('migration falhou');
    }))->toThrow(RuntimeException::class, 'migration falhou');

    expect(config('database.connections.pgsql.host'))->toBe('pgbouncer.exemplo');
});

it('durante() devolve o resultado da ação', function () {
    habilitarConexaoDireta();

    expect(ConexaoDireta::durante(fn () => 42))->toBe(42);
});
