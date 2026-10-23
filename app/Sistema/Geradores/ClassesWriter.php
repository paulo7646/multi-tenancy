<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdFormularioBotao;
use App\Models\Md\MdFormularioPagina;
use App\Sistema\Enums\TipoTabPage;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdGerenciamentoBotao;
use App\Models\Md\MdTabela;

/**
 * Gera as classes de personalização de cada tabela, na pasta dela:
 *  - ProdutoResource.php      (BaseResource): a tela do Filament, nome_arquivo do gerenciamento
 *    e ProdutoResource/Pages/  (Listar, Criar, Alterar, Visualizar)
 *  - BotoesProduto.php        (BaseBotoes): nome_arquivo dos botões, um método por botão
 *
 * Os arquivos são criados uma vez; depois o dicionário só acrescenta os
 * métodos de botões novos. O código escrito neles nunca é sobrescrito.
 */
class ClassesWriter
{
    public const PAGINAS = [
        'Listar' => 'ListarRegistros',
        'Criar' => 'CriarRegistro',
        'Alterar' => 'AlterarRegistro',
        'Visualizar' => 'VisualizarRegistro',
    ];

    public static function resourcePath(MdGerenciamento $gerenciamento): string
    {
        return $gerenciamento->tabela->caminho($gerenciamento->classeResource() . '.php');
    }

    public static function paginasPath(MdGerenciamento $gerenciamento): string
    {
        return $gerenciamento->tabela->caminho($gerenciamento->classeResource() . '/Pages');
    }

    public static function botoesPath(MdTabela $tabela, string $classe): string
    {
        return $tabela->caminho("{$classe}.php");
    }

    public static function controllerPath(MdTabela $tabela): string
    {
        return $tabela->caminho($tabela->classeController() . '.php');
    }

    /**
     * Controller de regras de negócio da tabela (criado uma vez).
     */
    public static function controller(MdTabela $tabela): void
    {
        Arquivos::gravarSeNaoExistir(static::controllerPath($tabela), <<<PHP
<?php

namespace {$tabela->namespace()};

use {$tabela->modulo->namespace()}\Enums;
use App\Sistema\Base\BaseController;
use App\Sistema\ModelProperty;

/**
 * Regras de negócio da tabela "{$tabela->nome}" ({$tabela->nome_fisico}).
 *
 * Criado pelo dicionário de dados; o código escrito aqui nunca é sobrescrito.
 * Use de qualquer lugar com \$this->getController('{$tabela->modulo->prefixo}', '{$tabela->nomeSemPrefixo()}').
 *
 * Exemplo:
 *
 *   public function reajustar(int \$id, float \$percentual): void
 *   {
 *       \$registro = new ModelProperty('{$tabela->modulo->prefixo}', '{$tabela->nomeSemPrefixo()}', \$id);
 *       \$registro->setDecimal('preco', \$registro->getDecimal('preco') * (1 + \$percentual / 100));
 *       \$registro->update();
 *   }
 */
class {$tabela->classeController()} extends BaseController
{
}

PHP);
    }

    /**
     * Resource do Filament do gerenciamento (ProdutoResource.php) e as páginas
     * dele (ProdutoResource/Pages/Listar.php...). Criados uma vez: o código
     * escrito neles nunca é sobrescrito.
     */
    public static function resource(MdGerenciamento $gerenciamento): void
    {
        $tabela = $gerenciamento->tabela;
        $classe = $gerenciamento->classeResource();
        $namespace = $tabela->namespace();

        Arquivos::gravarSeNaoExistir(static::resourcePath($gerenciamento), <<<PHP
<?php

namespace {$namespace};

use {$tabela->modulo->namespace()}\Enums;
use App\Sistema\Base\BaseResource;

/**
 * Tela "{$gerenciamento->titulo}" da tabela {$tabela->nome_fisico} (Resource do Filament).
 *
 * Formulário, colunas, filtros e botões vêm do dicionário de dados.
 * Permissões no Shield (Funções): {$gerenciamento->permissao('viewAny')}, {$gerenciamento->permissao('create')}...
 * Sobrescreva os métodos de App\Sistema\Base\BaseResource para personalizar:
 *
 *   // formulário (\$this->operacao: incluir, alterar, visualizar; \$this->registro)
 *   public function getReadOnly(): bool
 *   public function getCamposSomenteLeitura(): array         // ['campo', ...]
 *   public function getCamposOcultos(): array
 *   public function personalizarCampo(string \$nomeFisico, Component \$componente): Component
 *   public function antesDePreencher(array \$dados): array
 *   public function antesDeSalvar(array \$dados): array      // pode chamar \$this->abortar('...')
 *   public function depoisDeSalvar(Model \$registro): void
 *
 *   // listagem
 *   public function query(Builder \$query): Builder
 *   public function personalizarColuna(string \$nomeFisico, Column \$coluna): Column
 *   public function filtros(): array
 *
 *   // regras além da permissão do papel
 *   public function podeIncluir(): bool
 *   public function podeAlterar(Model \$registro): bool
 *   public function podeExcluir(Model \$registro): bool
 *   public function botaoVisivel(string \$nomeFisico, ?Model \$registro = null): bool
 */
class {$classe} extends BaseResource
{
    protected static string \$gerenciamento = '{$gerenciamento->nome_fisico}';
}

PHP);

        // "\{" não interpola dentro do heredoc: os nomes completos são montados antes
        $namespacePaginas = $namespace . '\\' . $classe . '\\Pages';
        $resourceFqcn = $namespace . '\\' . $classe;

        foreach (static::PAGINAS as $pagina => $base) {
            $baseFqcn = 'App\\Sistema\\Base\\Pages\\' . $base;

            Arquivos::gravarSeNaoExistir(static::paginasPath($gerenciamento) . "/{$pagina}.php", <<<PHP
<?php

namespace {$namespacePaginas};

use {$baseFqcn};
use {$resourceFqcn};

class {$pagina} extends {$base}
{
    protected static string \$resource = {$classe}::class;
}

PHP);
        }
    }

    /**
     * Tab pages "Customizada": acrescenta o método tabpage{NomeFisico}() nos
     * Resources das telas que usam o formulário (nada é sobrescrito).
     *
     * @return list<string> arquivos alterados
     */
    public static function tabPagesCustomizadas(MdFormulario $formulario): array
    {
        $tabPages = $formulario->tabPages()->where('tipo', TipoTabPage::Customizada->value)->get();

        if ($tabPages->isEmpty()) {
            return [];
        }

        // telas com este formulário (ou sem formulário próprio, usando o padrão da tabela)
        $gerenciamentos = $formulario->tabela->gerenciamentos()->with('tabela.modulo')->get()
            ->filter(fn (MdGerenciamento $g) => $g->md_formulario_id === $formulario->id
                || ($g->md_formulario_id === null && $formulario->tabela->formularioPadrao()?->id === $formulario->id));

        $alterados = [];

        foreach ($gerenciamentos as $gerenciamento) {
            $path = static::resourcePath($gerenciamento);

            if (! is_file($path)) {
                continue;
            }

            $codigo = file_get_contents($path);

            $novos = $tabPages
                ->reject(fn (MdFormularioPagina $p) => preg_match('/function\s+' . preg_quote(MdFormularioPagina::metodoCustomizado($p->nome_fisico), '/') . '\s*\(/i', $codigo))
                ->map(fn (MdFormularioPagina $p) => <<<PHP

    /**
     * Tab page "{$p->nome}" (customizada): os componentes do Filament dela.
     * \$this->registro é o registro aberto (null ao incluir) e \$this->operacao a tela.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public function {$p->metodoCustomizado($p->nome_fisico)}(): array
    {
        // TODO: montar o conteúdo da tab, ex.: [\Filament\Forms\Components\TextInput::make('...')]
        return [];
    }

PHP)
                ->implode('');

            if ($novos !== '') {
                Arquivos::gravar($path, substr_replace($codigo, rtrim($novos) . "\n", strrpos($codigo, '}'), 0));
                $alterados[] = Arquivos::relativo($path);
            }
        }

        return $alterados;
    }

    /**
     * Cria as classes de botões (uma por nome_arquivo) e acrescenta os métodos que faltam.
     *
     * @return list<string> arquivos de botões da tabela
     */
    public static function botoes(MdTabela $tabela): array
    {
        $model = $tabela->classeModel();
        $botoes = collect();

        foreach ($tabela->formularios()->with('botoes')->get() as $formulario) {
            foreach ($formulario->botoes as $botao) {
                $botoes->push([$botao, "formulário \"{$formulario->titulo}\"", "?{$model} \$registro, array \$dados"]);
            }
        }

        foreach ($tabela->gerenciamentos()->with('botoes')->get() as $gerenciamento) {
            foreach ($gerenciamento->botoes as $botao) {
                $botoes->push([$botao, "gerenciamento \"{$gerenciamento->titulo}\", " . static::ondeFica($botao), $botao->assinatura($model)]);
            }
        }

        $arquivos = [];

        foreach ($botoes->groupBy(fn (array $item) => $item[0]->nome_arquivo ?: $tabela->classeBotoesPadrao()) as $classe => $itens) {
            $path = static::botoesPath($tabela, $classe);
            Arquivos::gravarSeNaoExistir($path, static::esqueletoBotoes($tabela, $classe));

            $codigo = file_get_contents($path);

            $novos = $itens
                ->unique(fn (array $item) => $item[0]->nome_fisico)
                ->reject(fn (array $item) => preg_match('/function\s+' . preg_quote($item[0]->nome_fisico, '/') . '\s*\(/i', $codigo))
                ->map(fn (array $item) => static::metodo(...$item))
                ->implode("\n");

            if ($novos !== '') {
                // linha em branco entre o último método existente e os novos
                if (preg_match('/function\s+\w+\s*\(/', $codigo)) {
                    $novos = "\n{$novos}";
                }

                // insere antes da última chave (fim da classe)
                Arquivos::gravar($path, substr_replace($codigo, $novos, strrpos($codigo, '}'), 0));
            }

            $arquivos[] = Arquivos::relativo($path);
        }

        return $arquivos;
    }

    protected static function ondeFica(MdGerenciamentoBotao $botao): string
    {
        return match (true) {
            $botao->no_registro => 'no registro',
            $botao->exige_selecao && $botao->multiplos => 'topo, um ou mais registros selecionados',
            $botao->exige_selecao => 'topo, um registro selecionado',
            default => 'topo',
        };
    }

    protected static function esqueletoBotoes(MdTabela $tabela, string $classe): string
    {
        return <<<PHP
<?php

namespace {$tabela->namespace()};

use {$tabela->modulo->namespace()}\Enums;
use App\Sistema\Base\BaseBotoes;
use Illuminate\Support\Collection;

/**
 * Botões da tabela "{$tabela->nome}" ({$tabela->nome_fisico}): um método por
 * botão (nome físico) dos formulários e gerenciamentos com este nome de arquivo.
 *
 * Criado pelo dicionário de dados: botões novos ganham o método aqui e nada
 * é sobrescrito. Retorne uma URL para redirecionar, false para não mostrar
 * nada, ou nada para mostrar "concluído". \$this->abortar('...') desfaz a
 * transação e mostra a mensagem.
 */
class {$classe} extends BaseBotoes
{
}

PHP;
    }

    protected static function metodo(MdFormularioBotao|MdGerenciamentoBotao $botao, string $onde, string $assinatura): string
    {
        return <<<PHP
    /**
     * Botão "{$botao->titulo}" ({$onde}).
     */
    public function {$botao->nome_fisico}({$assinatura}): mixed
    {
        // TODO: implementar a ação do botão

        return null;
    }

PHP;
    }
}
