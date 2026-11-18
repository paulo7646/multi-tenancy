<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Models\Tenant;
use App\Sistema\ConexaoDireta;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoTabela;
use App\Sistema\Registro;
use App\Sistema\Runtime\Layout;
use App\Sistema\Schema\MigrationWriter;
use App\Sistema\Schema\SchemaException;
use App\Sistema\Schema\SchemaSnapshot;
use App\Sistema\Schema\StatusNoTenant;
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
        static::testarMigration($tabela, static::codigoMigration($tabela, SchemaSnapshot::fromState($campos, $controlaSituacao, $situacoes)));
    }

    /**
     * O mesmo teste, a partir da tabela como está salva no dicionário (botão Criar arquivos).
     *
     * @throws SchemaException
     */
    public static function verificarTabelaSalva(MdTabela $tabela): void
    {
        $tabela->loadMissing('modulo');

        static::testarMigration($tabela, static::codigoMigration($tabela, SchemaSnapshot::of($tabela)));
    }

    /**
     * @throws SchemaException
     */
    protected static function testarMigration(MdTabela $tabela, ?string $codigo): void
    {
        if ($codigo === null) {
            return;
        }

        $arquivo = storage_path('framework/md-check-'.bin2hex(random_bytes(6)).'.php');
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

    // ------------------------------------------------------------------
    // Prévias (o que o botão Criar arquivos vai fazer; não grava nada)
    // ------------------------------------------------------------------

    /**
     * @return list<string>
     */
    public static function previaTabela(MdTabela $tabela): array
    {
        $tabela->loadMissing(['modulo', 'itens']);

        $codigo = static::codigoMigration($tabela, SchemaSnapshot::of($tabela));
        $onde = $tabela->banco === Banco::Central ? 'na base pai' : 'em '.Tenant::count().' cliente(s)';

        $linhas = [$codigo === null
            ? 'Banco: nenhuma alteração na estrutura da tabela '.$tabela->nome_fisico.'.'
            : 'Migration de '.($tabela->isGerada() ? 'alteração' : 'criação').' da tabela '.$tabela->nome_fisico.', aplicada agora '.$onde.'.'];

        if ($tabela->tipoTabela()->temItens()) {
            $linhas[] = 'Valores da lista: '.$tabela->itens->count().' cadastrado(s); uma migration grava os que mudaram.';
        }

        $linhas[] = 'Model '.Arquivos::relativo(ModelWriter::path($tabela)).' (só o bloco de estrutura é recriado).';
        $linhas[] = 'Controller '.Arquivos::relativo(ClassesWriter::controllerPath($tabela)).(is_file(ClassesWriter::controllerPath($tabela)) ? ' (já existe: mantido).' : ' (novo).');
        $linhas[] = 'Arquivo do dicionário em database/md.';

        return $linhas;
    }

    /**
     * @return list<string>
     */
    public static function previaFormulario(MdFormulario $formulario): array
    {
        return [
            'Classe de botões da tabela (só os botões novos entram).',
            'Métodos das tab pages customizadas do formulário.',
            'Arquivo do dicionário em database/md.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function previaGerenciamento(MdGerenciamento $gerenciamento): array
    {
        $gerenciamento->loadMissing('tabela.modulo');
        $caminho = ClassesWriter::resourcePath($gerenciamento);
        $nome = strtolower($gerenciamento->nome_fisico);
        $novasPermissoes = glob($gerenciamento->tabela->banco->migrationsPath($gerenciamento->tabela->modulo)."/*_permissao_resource_{$nome}.php") === [];

        return [
            'Resource '.Arquivos::relativo($caminho).' e páginas'.(is_file($caminho) ? ' (já existem: mantidos).' : ' (novos).'),
            $novasPermissoes ? 'Migration com as permissões da tela, concedidas aos papéis do módulo.' : 'Permissões da tela: já criadas.',
            'Botões da tabela e arquivo do dicionário em database/md.',
        ];
    }

    /**
     * @return list<string>
     */
    public static function previaModulo(Modulo $modulo): array
    {
        $mudou = static::papeis($modulo->roles) !== ($modulo->roles_gerados === null ? null : static::papeis($modulo->roles_gerados));

        return [
            'Pasta '.Arquivos::relativo($modulo->caminho()).'.',
            $mudou ? 'Migration com a permissão '.$modulo->permissao().' e os papéis escolhidos.' : 'Permissão do módulo: sem mudanças.',
            'Arquivo do dicionário em database/md.',
        ];
    }

    /**
     * @return list<string> resumo do que foi gerado
     */
    public static function publicarTabela(MdTabela $tabela): array
    {
        static::recusarExistente($tabela);

        $tabela->refresh()->load('modulo');
        static::garantirCamposDoSistema($tabela);
        $tabela->unsetRelation('campos');
        $snapshot = SchemaSnapshot::of($tabela);
        $codigo = static::codigoMigration($tabela, $snapshot);
        $resumo = [];

        if ($codigo !== null) {
            $resumo[] = static::gravarEAplicar($tabela->banco, ($tabela->isGerada() ? 'alter' : 'create')."_{$tabela->nome_fisico}_table", $codigo, $tabela->modulo);
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

        return [...$resumo, 'Model '.Arquivos::relativo(ModelWriter::path($tabela)), 'Controller '.Arquivos::relativo(ClassesWriter::controllerPath($tabela)), ...array_map(fn ($a) => "Botões {$a}", $botoes)];
    }

    /**
     * Gera a migration que apaga a tabela e remove o model. As classes de
     * personalização (view, gerenciamento, botões) são mantidas: têm código seu.
     */
    /**
     * Tabela feita à mão (incorporada ao dicionário): a estrutura e o código são mantidos à mão, então o
     * dicionário nunca gera migration nem arquivos por cima, nem apaga a tabela.
     */
    protected static function recusarExistente(MdTabela $tabela): void
    {
        if ($tabela->existente) {
            throw new RuntimeException("A tabela {$tabela->nome_fisico} já existe no sistema (feita à mão): o dicionário só a cadastra, não gera nem altera arquivos dela.");
        }
    }

    public static function excluirTabela(MdTabela $tabela): void
    {
        static::recusarExistente($tabela);

        $referencias = $tabela->referenciadaPor()->with('tabela')->get()
            ->reject(fn ($campo) => $campo->md_tabela_id === $tabela->id);

        if ($referencias->isNotEmpty()) {
            throw new RuntimeException('Esta tabela é chave estrangeira em: '
                .$referencias->map(fn ($campo) => "{$campo->tabela->nome_fisico}.{$campo->nome_fisico}")->join(', ')
                .'. Remova esses campos antes.');
        }

        // as telas (gerenciamentos) saem junto com a tabela: as permissões delas também
        foreach ($tabela->gerenciamentos()->with('tabela.modulo')->get() as $gerenciamento) {
            static::excluirGerenciamento($gerenciamento);
        }

        if ($tabela->isGerada()) {
            static::gravarEAplicar($tabela->banco, "drop_{$tabela->nome_fisico}_table", MigrationWriter::drop($tabela, $tabela->generated_schema ?? SchemaSnapshot::empty()), $tabela->modulo);
        }

        ModelWriter::apagar($tabela);
        EnumWriter::apagar($tabela);
        Exportador::removerTabela($tabela);
        Registro::limpar();
    }

    /**
     * Lista e conteúdo têm campos do sistema (NOME e VALOR), criados e travados aqui.
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
     * reaproveitada) e, se mudaram, a migration que grava os registros na tabela.
     * O enum não é gerado aqui: é o botão Gerar constantes (gerarConstantes).
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
            $maior = max((int) $tabela->itens->max('codigo'), (int) collect($tabela->itens_gerados ?? [])->max('VALOR'));

            foreach ($tabela->itens->whereNull('codigo') as $item) {
                $item->forceFill(['codigo' => ++$maior])->saveQuietly();
            }

            $tabela->load('itens');
        }

        $chave = $tipo->colunaChave();
        $itens = $tabela->itens->map(fn ($item) => [
            $chave => $tipo === TipoTabela::Lista ? (int) $item->codigo : (string) $item->valor,
            'NOME' => $item->nome,
        ])->values()->all();

        $resumo = [];

        if ($itens !== ($tabela->itens_gerados ?? [])) {
            $codigo = static::arquivoMigrationItens($tabela->nome_fisico, $chave, $itens, $tabela->itens_gerados ?? []);
            $resumo[] = static::gravarEAplicar($tabela->banco, "itens_{$tabela->nome_fisico}", $codigo, $tabela->modulo);
            $tabela->forceFill(['itens_gerados' => $itens])->saveQuietly();
        }

        return $resumo;
    }

    /**
     * Botão Gerar constantes: recria os enums de todas as tabelas lista/conteúdo do módulo.
     *
     * @return list<string>
     */
    public static function gerarConstantes(Modulo $modulo): array
    {
        $resumo = [];

        foreach ($modulo->tabelas()->with('itens')->orderBy('nome_fisico')->get() as $tabela) {
            if (! $tabela->tipoTabela()->temItens()) {
                continue;
            }

            $tabela->setRelation('modulo', $modulo);
            EnumWriter::gravar($tabela);
            $resumo[] = 'Enum '.Arquivos::relativo($tabela->enumPath());
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

        return array_filter(['Resource '.Arquivos::relativo(ClassesWriter::resourcePath($gerenciamento)), $permissoes, ...array_map(fn ($a) => "Botões {$a}", $botoes)]);
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
        $nome = 'permissao_resource_'.strtolower($gerenciamento->nome_fisico);

        if (glob($banco->migrationsPath($gerenciamento->tabela->modulo)."/*_{$nome}.php") !== []) {
            return null;
        }

        $permissoes = $gerenciamento->permissoes();

        $codigo = static::arquivoMigration(
            'Permissoes::criar('.Arquivos::exportar($permissoes).', '.Arquivos::exportar(static::papeis($gerenciamento->tabela->modulo->roles)).');',
            'Permissoes::remover(...'.Arquivos::exportar($permissoes).');',
        );

        return static::gravarEAplicar($banco, $nome, $codigo, $gerenciamento->tabela->modulo);
    }

    /**
     * Ao excluir o gerenciamento: migration que remove as permissões dele.
     */
    public static function excluirGerenciamento(MdGerenciamento $gerenciamento): void
    {
        $banco = $gerenciamento->tabela->banco;
        $slug = strtolower($gerenciamento->nome_fisico);

        if (glob($banco->migrationsPath($gerenciamento->tabela->modulo)."/*_permissao_resource_{$slug}.php") === []) {
            return;
        }

        $permissoes = $gerenciamento->permissoes();

        static::gravarEAplicar($banco, "remover_resource_{$slug}", static::arquivoMigration(
            'Permissoes::remover(...'.Arquivos::exportar($permissoes).');',
            'Permissoes::criar('.Arquivos::exportar($permissoes).', []);',
        ), $gerenciamento->tabela->modulo);
    }

    /**
     * Situações: ícone e cor são lidos na hora; muda o JSON do git e a cópia em cada cliente.
     */
    public static function publicarStatus(): array
    {
        Exportador::status();
        StatusNoTenant::sincronizarTodos();
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
                : "Permissoes::sincronizar('{$permissao}', ".Arquivos::exportar($anteriores).');';

            $codigo = static::arquivoMigration(
                "Permissoes::sincronizar('{$permissao}', ".Arquivos::exportar($roles).');',
                $down,
            );

            $resumo[] = static::gravarEAplicar($modulo->banco, "permissao_modulo_{$modulo->prefixo}", $codigo, $modulo);

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
                "Permissoes::sincronizar('{$modulo->permissao()}', ".Arquivos::exportar(static::papeis($modulo->roles_gerados)).');',
            );

            static::gravarEAplicar($modulo->banco, "remover_modulo_{$modulo->prefixo}", $codigo, $modulo);
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

    protected static function gravarEAplicar(Banco $banco, string $nome, string $codigo, ?Modulo $modulo = null): string
    {
        $pasta = $banco->migrationsPath($modulo);
        $base = date('Y_m_d_His')."_{$nome}";
        $arquivo = "{$pasta}/{$base}.php";

        for ($i = 2; is_file($arquivo); $i++) {
            $arquivo = "{$pasta}/{$base}_{$i}.php";
        }

        Arquivos::gravar($arquivo, $codigo);
        $relativo = Arquivos::relativo($arquivo);

        try {
            if ($banco === Banco::Central) {
                static::artisan('migrate', ['--path' => $arquivo, '--realpath' => true, '--force' => true]);
            } elseif ($modulo) {
                // só as empresas que têm o módulo; as demais recebem pela release (Releases\Aplicador)
                $empresas = static::empresasDoModulo($modulo);

                if ($empresas !== []) {
                    static::artisan('tenants:migrate', ['--tenants' => $empresas, '--path' => $arquivo, '--realpath' => true, '--force' => true]);
                }
            } else {
                static::artisan('tenants:migrate', ['--force' => true]);
            }
        } catch (Throwable $e) {
            // central: a migration roda em transação, nada ficou aplicado
            if ($banco === Banco::Central) {
                Arquivos::apagar($arquivo);
            }

            throw new RuntimeException("Migration {$relativo} falhou: ".static::erroAmigavel($e), previous: $e);
        }

        if ($banco === Banco::Tenant && $modulo) {
            return "Migration {$relativo} (aplicada nas empresas que têm o módulo; as demais recebem ao publicar uma release)";
        }

        return "Migration {$relativo} (aplicada aqui; no cliente: php artisan {$banco->comandoMigrate()})";
    }

    /**
     * Ids das empresas que têm o módulo: todas, se ele é padrão; senão as cadastradas na aba Módulos.
     *
     * @return list<string>
     */
    protected static function empresasDoModulo(Modulo $modulo): array
    {
        if ($modulo->padrao) {
            return Tenant::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        return DB::connection('central')->table('empresa_modulos')->where('modulo_id', $modulo->id)->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();
    }

    protected static function artisan(string $comando, array $parametros): void
    {
        // migrate e afins vão direto ao PostgreSQL (atrás de um PgBouncer) e voltam mesmo se falharem
        $codigo = ConexaoDireta::vale($comando)
            ? ConexaoDireta::durante(fn () => Artisan::call($comando, $parametros))
            : Artisan::call($comando, $parametros);
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
            ->mapWithKeys(fn (Tenant $tenant) => [$tenant->displayName() => fn (Closure $callback) => $tenant->executar($callback)])
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
                    .'Remova as ligações antes, ou no dicionário mude a chave estrangeira para "Excluir junto".';
            }

            $mensagem = preg_replace('/^SQLSTATE\[\w+\]: [^:]+: \d+ /', '', $original);

            return 'O banco recusou a alteração: '.trim(strtok($mensagem, "\n"));
        }

        return $e->getMessage();
    }
}
