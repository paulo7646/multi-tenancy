<?php

namespace Tests\Support;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Geradores\Gerador;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tabelas de verdade geradas pelo dicionário (migration, model e controller) para os testes, no banco
 * central. Quem usa chama limpar() no afterEach: apaga a tabela física, os arquivos de app/Modulos, as
 * migrations e o cadastro. As md_* ficam na conexão "central" (sem rollback), por isso a limpeza explícita.
 */
class TabelasDeTeste
{
    /** @var list<MdTabela> */
    protected static array $tabelas = [];

    /** @var list<Modulo> */
    protected static array $modulos = [];

    protected static ?string $modulosJson = null;

    /** @var list<string> */
    protected static array $migrationsAntes = [];

    public static function modulo(): Modulo
    {
        if (static::$modulosJson === null) {
            static::$modulosJson = file_get_contents(base_path('database/md/modulos.json'));
            static::$migrationsAntes = glob(database_path('migrations/*.php')) ?: [];
        }

        $sufixo = strtoupper(Str::random(4));

        return static::$modulos[] = Modulo::create([
            'nome' => "Teste {$sufixo}", 'prefixo' => 'z'.strtolower(Str::random(3)),
            'pasta' => "Teste{$sufixo}", 'banco' => 'central', 'roles' => [],
        ]);
    }

    /**
     * Cria e gera a tabela. $campos: [[nome físico, tipo, extras]], ex.: ['NOME', 'string', ['tamanho' => 60]].
     * Com $pai, ganha uma FK (PAI_ID) para a tabela pai.
     *
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $campos
     */
    public static function tabela(Modulo $modulo, string $nome, array $campos, ?MdTabela $pai = null): MdTabela
    {
        $tabela = static::$tabelas[] = MdTabela::create([
            'modulo_id' => $modulo->id, 'nome' => Str::headline($nome), 'nome_fisico' => strtoupper("{$modulo->prefixo}_{$nome}"),
            'banco' => 'central', 'tipo' => 'cadastro',
        ]);

        $ordem = 0;

        foreach ($campos as $campo) {
            [$fisico, $tipo] = $campo;

            MdCampo::create(['uid' => (string) Str::uuid(), 'md_tabela_id' => $tabela->id, 'nome' => Str::headline(strtolower($fisico)), 'nome_fisico' => $fisico, 'tipo' => $tipo, 'ordem' => ++$ordem, ...($campo[2] ?? [])]);
        }

        if ($pai) {
            MdCampo::create(['uid' => (string) Str::uuid(), 'md_tabela_id' => $tabela->id, 'nome' => 'Pai', 'nome_fisico' => 'PAI_ID', 'tipo' => 'foreignId', 'fk_tabela_id' => $pai->id, 'fk_on_delete' => 'cascade', 'obrigatorio' => true, 'ordem' => ++$ordem]);
        }

        Gerador::publicarTabela($tabela->fresh());

        return $tabela->fresh(['modulo']);
    }

    public static function limpar(): void
    {
        foreach (array_reverse(static::$tabelas) as $tabela) {
            Schema::connection('central')->dropIfExists($tabela->nome_fisico);
            File::delete(base_path("database/md/tabelas/central_{$tabela->nome_fisico}.json"));
        }

        foreach (static::$modulos as $modulo) {
            File::deleteDirectory(app_path("Modulos/{$modulo->pasta}"));
        }

        @rmdir(base_path('database/md/tabelas'));

        if (static::$modulosJson !== null) {
            file_put_contents(base_path('database/md/modulos.json'), static::$modulosJson);

            foreach (array_diff(glob(database_path('migrations/*.php')) ?: [], static::$migrationsAntes) as $novo) {
                File::delete($novo);
            }
        }

        foreach (array_reverse(static::$tabelas) as $tabela) {
            MdTabela::whereKey($tabela->id)->first()?->delete();
        }

        foreach (static::$modulos as $modulo) {
            Modulo::whereKey($modulo->id)->first()?->delete();
        }

        static::$tabelas = static::$modulos = static::$migrationsAntes = [];
        static::$modulosJson = null;
    }
}
