<?php

use App\Jobs\CreateTenantUser;
use App\Models\Md\Modulo;
use App\Models\Tenant;
use App\Sistema\Enums\Banco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A conexão "central" (módulos, licenças...) é outra conexão ao mesmo banco: sem dividir a conexão da
 * transação do teste, ela não enxerga o que o teste criou na conexão padrão (nem as chaves estrangeiras).
 * Só para os testes que misturam as duas: o código que abre transação na central (dicionário) não
 * pode dividir, senão dá "already an active transaction".
 */
function compartilharConexaoCentral(): void
{
    // a "central" passa a ser o mesmo objeto de conexão do teste: divide a conexão física E o controle de
    // transação (só dividir o PDO faz o código que abre transação na central dar "already an active transaction")
    $gerenciador = app('db');
    $propriedade = new ReflectionProperty($gerenciador, 'connections');
    $conexoes = $propriedade->getValue($gerenciador);
    $conexoes['central'] = $gerenciador->connection();
    $propriedade->setValue($gerenciador, $conexoes);
}

/**
 * Empresa de teste com banco próprio, migrations e o administrador criados de verdade.
 * Quem chama apaga com $tenant->delete() no finally (o banco do cliente cai junto).
 */
function empresaComBanco(string $id): Tenant
{
    Event::fake([TenantCreated::class]); // o banco é criado abaixo, à mão
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $tenant = Tenant::create(['id' => $id, 'name' => 'admin', 'email' => "adm@{$id}.test", 'is_active' => true, 'password' => 'Senha@123']);

    dispatch_sync(new CreateDatabase($tenant));
    Artisan::call('tenants:migrate', ['--tenants' => [$id], '--force' => true]);
    dispatch_sync(new CreateTenantUser($tenant));

    return $tenant;
}

/**
 * Módulo de cliente de teste com uma migration de verdade na pasta dele (migrations/tenant/modulos/{prefixo}).
 * Quem chama apaga a pasta no afterEach.
 */
function moduloComMigration(string $prefixo, string $tabela): Modulo
{
    $modulo = Modulo::create(['nome' => ucfirst($prefixo), 'prefixo' => $prefixo, 'pasta' => ucfirst($prefixo), 'banco' => Banco::Tenant]);

    $pasta = Banco::Tenant->migrationsPath($modulo);
    File::ensureDirectoryExists($pasta);
    File::put("{$pasta}/2026_11_18_000001_create_{$tabela}_table.php", <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tabela}', function (Blueprint \$table) {
            \$table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tabela}');
    }
};
PHP);

    return $modulo;
}
