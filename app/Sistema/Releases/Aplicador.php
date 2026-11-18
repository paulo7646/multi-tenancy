<?php

namespace App\Sistema\Releases;

use App\Models\Md\MdRelease;
use App\Models\Md\Modulo;
use App\Models\Tenant;
use App\Sistema\AtualizadorDeClientes;
use App\Sistema\ConexaoDireta;
use App\Sistema\Enums\Banco;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Aplica as releases na empresa: só as dos módulos que ela tem (os padrão e os cadastrados nela) e só as
 * migrations que a release publicou. Quem usa 2 módulos não baixa as atualizações do 3º.
 *
 * Cada release é cumulativa (lista todas as migrations do módulo até ali), então basta aplicar a última
 * pendente de cada módulo; as anteriores ficam marcadas como aplicadas. Assim uma migration que mudou
 * depois da v1 não trava a empresa, e quem entra agora roda cada arquivo uma vez só.
 */
class Aplicador
{
    /**
     * Ids dos módulos de cliente que a empresa tem: os padrão e os cadastrados nela.
     *
     * @return list<int>
     */
    public static function modulosDaEmpresa(Tenant $empresa): array
    {
        $padrao = Modulo::query()->where('banco', Banco::Tenant->value)->where('padrao', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique([...$padrao, ...$empresa->idsDosModulos()]));
    }

    /**
     * Releases dos módulos da empresa que ela ainda não aplicou, na ordem (módulo, versão). Sem o pacote
     * (que é grande): quem aplica busca o da última de cada módulo.
     *
     * @return Collection<int, MdRelease>
     */
    public static function pendentes(Tenant $empresa): Collection
    {
        $aplicadas = static::idsAplicados($empresa);

        return MdRelease::query()
            ->select(['id', 'modulo_id', 'versao', 'notas', 'publicado_em'])
            ->whereIn('modulo_id', static::modulosDaEmpresa($empresa))
            ->when($aplicadas !== [], fn ($q) => $q->whereNotIn('id', $aplicadas))
            ->orderBy('modulo_id')->orderBy('versao')
            ->get();
    }

    /**
     * Aplica as releases pendentes. Devolve quantas ficaram aplicadas.
     *
     * @throws RuntimeException se um arquivo mudou depois da release, uma migration falhou ou já há uma
     *                          atualização desta empresa em andamento
     */
    public static function aplicar(Tenant $empresa): int
    {
        set_time_limit(0);

        $trava = Cache::store('central')->lock("releases-{$empresa->getTenantKey()}", 900);

        if (! $trava->get()) {
            throw new RuntimeException('Já há uma atualização desta empresa em andamento. Tente de novo em instantes.');
        }

        // o tenants:migrate encerra o contexto da empresa ao terminar: se quem chamou está dentro de uma
        // (a aba Releases do cliente), ele volta ao que era, senão a sessão e a resposta rodariam sem empresa
        $original = tenant();

        try {
            static::garantirBase($empresa);

            $pendentes = static::pendentes($empresa);
            $padrao = Modulo::query()->where('padrao', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

            // módulos padrão (Estrutura) primeiro: os outros podem ter FK para eles
            $grupos = $pendentes->groupBy('modulo_id')
                ->sortBy(fn (Collection $grupo, $id) => (in_array((int) $id, $padrao, true) ? '0' : '1').str_pad((string) $id, 10, '0', STR_PAD_LEFT));

            foreach ($grupos as $grupo) {
                $ultima = MdRelease::with('modulo')->findOrFail($grupo->last()->id);

                static::aplicarPacote($empresa, $ultima);
                static::marcarAplicadas($empresa, $ultima, $grupo);
            }

            return $pendentes->count();
        } finally {
            $trava->release();

            if (tenant()?->getTenantKey() !== $original?->getTenantKey()) {
                $original ? tenancy()->initialize($original) : tenancy()->end();
            }
        }
    }

    /**
     * Roda as migrations do pacote (as que o banco da empresa ainda não tem), conferindo o hash de cada arquivo.
     */
    protected static function aplicarPacote(Tenant $empresa, MdRelease $release): void
    {
        $modulo = $release->modulo;

        // o prefixo entra no caminho da pasta e o nome do arquivo vem do pacote (JSON): nada de sair da pasta do módulo
        if (! preg_match('/^[a-z0-9_]{1,5}$/', (string) $modulo->prefixo)) {
            throw new RuntimeException('Prefixo de módulo inválido na release.');
        }

        $pasta = Banco::Tenant->migrationsPath($modulo);

        foreach ($release->migrations() as $migration) {
            if (! preg_match('/^[\w.\-]+\.php$/', (string) $migration['arquivo']) || basename($migration['arquivo']) !== $migration['arquivo']) {
                throw new RuntimeException("Nome de migration inválido na release v{$release->versao}.");
            }

            $caminho = "{$pasta}/{$migration['arquivo']}";

            if (! is_file($caminho) || hash_file('sha256', $caminho) !== $migration['hash']) {
                throw new RuntimeException("A migration {$migration['arquivo']} do módulo {$modulo->nome} mudou depois da release v{$release->versao}. Publique uma nova release.");
            }

            $codigo = ConexaoDireta::durante(fn () => Artisan::call('tenants:migrate', [
                '--tenants' => [$empresa->getTenantKey()],
                '--path' => $caminho,
                '--realpath' => true,
                '--force' => true,
            ]));
            $saida = trim(Artisan::output());

            // o nome físico de uma tabela pode conter "FAIL": só vale a palavra inteira
            if ($codigo !== 0 || preg_match('/\bFAIL\b/', $saida)) {
                throw new RuntimeException($saida !== '' ? $saida : "A migration {$migration['arquivo']} falhou.");
            }
        }
    }

    /**
     * Grava na empresa que a última release (e as anteriores do módulo, que ela já contém) foi aplicada.
     *
     * @param  Collection<int, MdRelease>  $grupo
     */
    protected static function marcarAplicadas(Tenant $empresa, MdRelease $ultima, Collection $grupo): void
    {
        $agora = now();
        $usuario = auth()->user()?->email;

        $empresa->executar(fn () => DB::table('releases_aplicadas')->insert(
            $grupo->map(fn (MdRelease $release) => [
                'release_id' => $release->id,
                'modulo' => $ultima->modulo->prefixo,
                'versao' => $release->versao,
                'aplicada_em' => $agora,
                'aplicada_por' => $usuario,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->all(),
        ));

        AtualizadorDeClientes::esquecerPendentes($empresa);
    }

    /**
     * Ids das releases já aplicadas pela empresa.
     *
     * @return list<int>
     */
    public static function idsAplicados(Tenant $empresa): array
    {
        return $empresa->executar(fn () => Schema::hasTable('releases_aplicadas')
            ? DB::table('releases_aplicadas')->pluck('release_id')->map(fn ($id) => (int) $id)->all()
            : []);
    }

    /**
     * Empresa antiga, sem a tabela das releases aplicadas: atualiza o banco-base antes.
     */
    protected static function garantirBase(Tenant $empresa): void
    {
        if (! $empresa->executar(fn () => Schema::hasTable('releases_aplicadas'))) {
            AtualizadorDeClientes::atualizar($empresa);
        }
    }
}
