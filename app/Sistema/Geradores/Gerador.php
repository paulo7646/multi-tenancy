<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Models\Tenant;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoTabela;
use App\Sistema\Registro;
use App\Sistema\Runtime\Layout;
use App\Sistema\Schema\MigrationWriter;
use App\Sistema\Schema\SchemaException;
use App\Sistema\Schema\SchemaSnapshot;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Gera os arquivos do dicionário de dados (vão para o git) e, em
 * desenvolvimento, já aplica as migrations. No cliente: migrate e tenants:migrate.
 */
class Gerador
{
    // ------------------------------------------------------------------
    // Tabelas
    // ------------------------------------------------------------------

    /**
     * Testa a migration que sairia do que está na tela: roda o up() numa
     * transação desfeita, no central ou em cada cliente.
     *
     * @throws SchemaException
     */
    public static function verificarTabela(MdTabela $tabela, array $campos, bool $controlaSituacao, array $situacoes): void
    {
        $codigo = static::codigoMigration($tabela, SchemaSnapshot::fromState($campos, $controlaSituacao, $situacoes));

        if ($codigo === null) {
            return;
        }

        $arquivo = storage_path('framework/md-check-' . bin2hex(random_bytes(6)) . '.php');
        Arquivos::gravar($arquivo, $codigo);

        try {
            $migration = require $arquivo;
            $problemas = [];

            foreach (static::bancos($tabela->banco) as $onde => $executar) {
                $erro = $executar(function () use ($migration) {
                    $connection = DB::connection();
                    $connection->beginTransaction();

                    try {
                        $migration->up();

                        return null;
                    } catch (Throwable $e) {
                        return static::erroAmigavel($e);
                    } finally {
                        $connection->rollBack();
                    }
                });

                if ($erro !== null) {
                    $problemas[$onde] = [$erro];
                }
            }

            if ($problemas !== []) {
                throw new SchemaException($problemas);
            }
        } finally {
            Arquivos::apagar($arquivo);
        }
    }

    /**
     * @return list<string> resumo do que foi gerado
     */
    public static function publicarTabela(MdTabela $tabela): array
    {
        $tabela->refresh()->load('modulo');
        static::garantirCamposDoSistema($tabela);
        $tabela->unsetRelation('campos');
        $snapshot = SchemaSnapshot::of($tabela);
        $codigo = static::codigoMigration($tabela, $snapshot);
        $resumo = [];

        if ($codigo !== null) {
            $resumo[] = static::gravarEAplicar($tabela->banco, ($tabela->isGerada() ? 'alter' : 'create') . "_{$tabela->nome_fisico}_table", $codigo);
        }

        $novos = array_diff(array_keys($snapshot['fields']), array_keys($tabela->generated_schema['fields'] ?? []));

        $tabela->forceFill(['generated_schema' => $snapshot, 'generated_at' => now()])->saveQuietly();

        // campos novos (com componente) entram no fim da tab "Geral" dos formulários
        // (a primeira página; as tab pages podem ser de outro tipo, como Tabela ou Customizada)
        $semComponente = $tabela->campos()->where('sem_componente', true)->pluck('uid')->all();
        $novos = array_values(array_diff($novos, $semComponente));

        foreach ($tabela->formularios as $formulario) {
            $layout = $formulario->layout();
            $faltam = array_values(array_diff($novos, Layout::uids($layout)));

            if ($faltam !== [] && $formulario->componentes()->exists()) {
                foreach ($faltam as $uid) {
                    $layout[0]['campos'][] = ['tipo' => Layout::CAMPO, 'campo' => $uid, 'colunas' => 1];
                }
                $formulario->salvarLayout($layout);
            }
        }

        // lista/conteúdo: enum do módulo e registros da tabela (a tabela já existe)
        $resumo = [...$resumo, ...static::publicarItens($tabela)];

        ModelWriter::gravar($tabela);
        ClassesWriter::controller($tabela);
        $botoes = ClassesWriter::botoes($tabela);
        Exportador::tabela($tabela);
        Registro::limpar();

        return [...$resumo, 'Model ' . Arquivos::relativo(ModelWriter::path($tabela)), 'Controller ' . Arquivos::relativo(ClassesWriter::controllerPath($tabela)), ...array_map(fn ($a) => "Botões {$a}", $botoes)];
    }

    /**
     * Gera a migration que apaga a tabela e remove o model. As classes de
     * personalização (view, gerenciamento, botões) são mantidas: têm código seu.
     */
    public static function excluirTabela(MdTabela $tabela): void
    {
        $referencias = $tabela->referenciadaPor()->with('tabela')->get()
            ->reject(fn ($campo) => $campo->md_tabela_id === $tabela->id);

        if ($referencias->isNotEmpty()) {
            throw new RuntimeException('Esta tabela é chave estrangeira em: '
                . $referencias->map(fn ($campo) => "{$campo->tabela->nome_fisico}.{$campo->nome_fisico}")->join(', ')
                . '. Remova esses campos antes.');
        }

        // as telas (gerenciamentos) saem junto com a tabela: as permissões delas também
        foreach ($tabela->gerenciamentos()->with('tabela.modulo')->get() as $gerenciamento) {
            static::excluirGerenciamento($gerenciamento);
        }

        if ($tabela->isGerada()) {
            static::gravarEAplicar($tabela->banco, "drop_{$tabela->nome_fisico}_table", MigrationWriter::drop($tabela, $tabela->generated_schema ?? SchemaSnapshot::empty()));
        }

        ModelWriter::apagar($tabela);
        EnumWriter::apagar($tabela);
        Exportador::removerTabela($tabela);
        Registro::limpar();
    }

    /**
     * Lista e conteúdo têm campos do sistema (CODIGO/VALOR e DESCRICAO), criados e travados aqui.
     */
    public static function garantirCamposDoSistema(MdTabela $tabela): void
    {
        foreach ($tabela->tipoTabela()->camposDoSistema() as $ordem => $definicao) {
            $campo = $tabela->campos()->where('nome_fisico', $definicao['nome_fisico'])->first();

            $campo
                ? $campo->update([...collect($definicao)->except('nome')->all(), 'sistema' => true])
                : $tabela->campos()->create([...$definicao, 'sistema' => true, 'ordem' => $ordem - 100]);
        }
    }

    /**
     * Itens das tabelas lista/conteúdo: códigos da lista (sequência, nunca
     * reaproveitada), o enum do módulo e, se mudaram, a migration que grava
     * os registros na tabela.
     *
     * @return list<string>
     */
    public static function publicarItens(MdTabela $tabela): array
    {
        $tipo = $tabela->tipoTabela();

        if (! $tipo->temItens()) {
            return [];
        }

        $tabela->load('itens');

        if ($tipo === TipoTabela::Lista) {
            $maior = max((int) $tabela->itens->max('codigo'), (int) collect($tabela->itens_gerados ?? [])->max('CODIGO'));

            foreach ($tabela->itens->whereNull('codigo') as $item) {
                $item->forceFill(['codigo' => ++$maior])->saveQuietly();
            }

            $tabela->load('itens');
        }

        $chave = $tipo->colunaChave();
        $itens = $tabela->itens->map(fn ($item) => [
            $chave => $tipo === TipoTabela::Lista ? (int) $item->codigo : (string) $item->valor,
            'DESCRICAO' => $item->descricao,
        ])->values()->all();

        EnumWriter::gravar($tabela);
        $resumo = ['Enum ' . Arquivos::relativo($tabela->enumPath())];

        if ($itens !== ($tabela->itens_gerados ?? [])) {
            $codigo = static::arquivoMigrationItens($tabela->nome_fisico, $chave, $itens, $tabela->itens_gerados ?? []);
            $resumo[] = static::gravarEAplicar($tabela->banco, "itens_{$tabela->nome_fisico}", $codigo);
            $tabela->forceFill(['itens_gerados' => $itens])->saveQuietly();
        }

        return $resumo;
    }

    protected static function arquivoMigrationItens(string $tabela, string $chave, array $itens, array $anteriores): string
    {
        $exportar = fn (array $lista) => var_export($lista, true);

        return <<<PHP
<?php

// Gerado pelo dicionário de dados: itens da tabela {$tabela}.

use App\Sistema\Schema\ItensDaTabela;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ItensDaTabela::sincronizar('{$tabela}', '{$chave}', {$exportar($itens)});
    }

    public function down(): void
    {
        ItensDaTabela::sincronizar('{$tabela}', '{$chave}', {$exportar($anteriores)});
    }
};

PHP;
    }

    protected static function codigoMigration(MdTabela $tabela, array $snapshot): ?string
    {
        return $tabela->isGerada()
            ? MigrationWriter::alter($tabela, $tabela->generated_schema ?? SchemaSnapshot::empty(), $snapshot)
            : MigrationWriter::create($tabela, $snapshot);
    }

    // ------------------------------------------------------------------
    // Formulários e gerenciamentos
    // ------------------------------------------------------------------

    public static function publicarFormulario(MdFormulario $formulario): array
    {
        $formulario->refresh()->load('tabela.modulo');

        // a tela do formulário é a do Resource do gerenciamento (lida do dicionário na hora)
        $botoes = ClassesWriter::botoes($formulario->tabela);
        $customizadas = ClassesWriter::tabPagesCustomizadas($formulario);
        Exportador::tabela($formulario->tabela);
        Registro::limpar();

        return [
            ...array_map(fn ($a) => "Botões {$a}", $botoes),
            ...array_map(fn ($a) => "Tab pages customizadas em {$a}", $customizadas),
        ];
    }

    public static function publicarGerenciamento(MdGerenciamento $gerenciamento): array
    {
        $gerenciamento->refresh()->load('tabela.modulo');

        ClassesWriter::resource($gerenciamento);

        // Resource novo: já com os métodos das tab pages customizadas do formulário dele
        if ($formulario = $gerenciamento->formulario ?? $gerenciamento->tabela->formularioPadrao()) {
            ClassesWriter::tabPagesCustomizadas($formulario->setRelation('tabela', $gerenciamento->tabela));
        }
        $botoes = ClassesWriter::botoes($gerenciamento->tabela);
        $permissoes = static::permissoesGerenciamento($gerenciamento);
        Exportador::tabela($gerenciamento->tabela);
        Registro::limpar();

        return array_filter(['Resource ' . Arquivos::relativo(ClassesWriter::resourcePath($gerenciamento)), $permissoes, ...array_map(fn ($a) => "Botões {$a}", $botoes)]);
    }

    /**
     * Na primeira publicação: migration que cria as permissões do Shield do
     * Resource (view_any_op_produto, create_op_produto...) no banco da tabela e
     * concede aos papéis do módulo, para começar igual ao acesso ao módulo.
     * Depois disso os papéis são ajustados no Shield (Funções).
     */
    public static function permissoesGerenciamento(MdGerenciamento $gerenciamento): ?string
    {
        $banco = $gerenciamento->tabela->banco;
        $nome = 'permissao_resource_' . strtolower($gerenciamento->nome_fisico);

        if (glob($banco->migrationsPath() . "/*_{$nome}.php") !== []) {
            return null;
        }

        $permissoes = $gerenciamento->permissoes();

        $codigo = static::arquivoMigration(
            'Permissoes::criar(' . Arquivos::exportar($permissoes) . ', ' . Arquivos::exportar(static::papeis($gerenciamento->tabela->modulo->roles)) . ');',
            'Permissoes::remover(...' . Arquivos::exportar($permissoes) . ');',
        );

        return static::gravarEAplicar($banco, $nome, $codigo);
    }

    /**
     * Ao excluir o gerenciamento: migration que remove as permissões dele.
     */
    public static function excluirGerenciamento(MdGerenciamento $gerenciamento): void
    {
        $banco = $gerenciamento->tabela->banco;
        $slug = strtolower($gerenciamento->nome_fisico);

        if (glob($banco->migrationsPath() . "/*_permissao_resource_{$slug}.php") === []) {
            return;
        }

        $permissoes = $gerenciamento->permissoes();

        static::gravarEAplicar($banco, "remover_resource_{$slug}", static::arquivoMigration(
            'Permissoes::remover(...' . Arquivos::exportar($permissoes) . ');',
            'Permissoes::criar(' . Arquivos::exportar($permissoes) . ', []);',
        ));
    }

    /**
     * Situações: ícone e cor são lidos na hora; só o JSON do git muda.
     */
    public static function publicarStatus(): array
    {
        Exportador::status();
        Registro::limpar();

        return [];
    }

    // ------------------------------------------------------------------
    // Módulos
    // ------------------------------------------------------------------

    /**
     * Cria a pasta do módulo e, se os papéis mudaram, a migration que
     * sincroniza a permissão modulo_{prefixo} no banco do módulo (central ou cada cliente).
     */
    public static function publicarModulo(Modulo $modulo): array
    {
        $modulo->refresh();
        $resumo = [];

        $roles = static::papeis($modulo->roles);
        $anteriores = $modulo->roles_gerados === null ? null : static::papeis($modulo->roles_gerados);

        if ($roles !== $anteriores) {
            $permissao = $modulo->permissao();
            $down = $anteriores === null
                ? "Permissoes::remover('{$permissao}');"
                : "Permissoes::sincronizar('{$permissao}', " . Arquivos::exportar($anteriores) . ');';

            $codigo = static::arquivoMigration(
                "Permissoes::sincronizar('{$permissao}', " . Arquivos::exportar($roles) . ');',
                $down,
            );

            $resumo[] = static::gravarEAplicar($modulo->banco, "permissao_modulo_{$modulo->prefixo}", $codigo);

            $modulo->forceFill(['roles_gerados' => $roles])->saveQuietly();
        }

        Arquivos::gravarSeNaoExistir($modulo->caminho('.gitkeep'), '');
        Exportador::modulos();
        Registro::limpar();

        return $resumo;
    }

    public static function excluirModulo(Modulo $modulo): void
    {
        if ($modulo->tabelas()->exists()) {
            throw new RuntimeException('O módulo tem tabelas. Exclua as tabelas antes.');
        }

        if ($modulo->roles_gerados !== null) {
            $codigo = static::arquivoMigration(
                "Permissoes::remover('{$modulo->permissao()}');",
                "Permissoes::sincronizar('{$modulo->permissao()}', " . Arquivos::exportar(static::papeis($modulo->roles_gerados)) . ');',
            );

            static::gravarEAplicar($modulo->banco, "remover_modulo_{$modulo->prefixo}", $codigo);
        }

        Arquivos::apagar($modulo->caminho('.gitkeep'));
        Registro::limpar();
    }

    protected static function papeis(?array $roles): array
    {
        $roles = array_values(array_unique(array_filter($roles ?? [])));
        sort($roles);

        return $roles;
    }

    protected static function arquivoMigration(string $up, string $down): string
    {
        return <<<PHP
<?php

// Gerado pelo dicionário de dados (modulos).

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        {$up}
    }

    public function down(): void
    {
        {$down}
    }
};

PHP;
    }

    // ------------------------------------------------------------------
    // Migrations
    // ------------------------------------------------------------------

    protected static function gravarEAplicar(Banco $banco, string $nome, string $codigo): string
    {
        $pasta = $banco->migrationsPath();
        $base = date('Y_m_d_His') . "_{$nome}";
        $arquivo = "{$pasta}/{$base}.php";

        for ($i = 2; is_file($arquivo); $i++) {
            $arquivo = "{$pasta}/{$base}_{$i}.php";
        }

        Arquivos::gravar($arquivo, $codigo);
        $relativo = Arquivos::relativo($arquivo);

        try {
            if ($banco === Banco::Central) {
                static::artisan('migrate', ['--path' => $arquivo, '--realpath' => true, '--force' => true]);
            } else {
                static::artisan('tenants:migrate', ['--force' => true]);
            }
        } catch (Throwable $e) {
            // central: a migration roda em transação, nada ficou aplicado
            if ($banco === Banco::Central) {
                Arquivos::apagar($arquivo);
            }

            throw new RuntimeException("Migration {$relativo} falhou: " . static::erroAmigavel($e), previous: $e);
        }

        return "Migration {$relativo} (aplicada aqui; no cliente: php artisan {$banco->comandoMigrate()})";
    }

    protected static function artisan(string $comando, array $parametros): void
    {
        $codigo = Artisan::call($comando, $parametros);
        $saida = Artisan::output();

        if ($codigo !== 0 || str_contains($saida, 'FAIL')) {
            throw new RuntimeException(trim($saida));
        }
    }

    /**
     * Bancos onde a tabela vive, cada um com um executor no contexto certo.
     *
     * @return array<string, Closure(Closure): mixed>
     */
    protected static function bancos(Banco $banco): array
    {
        if ($banco === Banco::Central) {
            return ['base pai' => fn (Closure $callback) => static::noCentral($callback)];
        }

        return Tenant::with('domains')->get()
            ->mapWithKeys(fn (Tenant $tenant) => [$tenant->displayName() => fn (Closure $callback) => $tenant->run($callback)])
            ->all();
    }

    /**
     * Roda o callback com a conexão padrão apontando para o central.
     */
    protected static function noCentral(Closure $callback): mixed
    {
        $anterior = DB::getDefaultConnection();
        DB::setDefaultConnection('central');

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($anterior);
        }
    }

    public static function erroAmigavel(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            $original = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            // FK impedindo a exclusão: diz quem está ligado (pelo nome do dicionário)
            if ((string) $e->getCode() === '23503' && preg_match('/(?:on|na) (?:table|tabela) "([^"]+)"\s*$/mi', strtok($original, "\n"), $m)) {
                $tabela = MdTabela::where('nome_fisico', $m[1])->value('nome') ?? $m[1];

                return "Não dá para excluir: existem registros em \"{$tabela}\" ligados a este. "
                    . 'Remova as ligações antes, ou no dicionário mude a chave estrangeira para "Excluir junto".';
            }

            $mensagem = preg_replace('/^SQLSTATE\[\w+\]: [^:]+: \d+ /', '', $original);

            return 'O banco recusou a alteração: ' . trim(strtok($mensagem, "\n"));
        }

        return $e->getMessage();
    }
}
