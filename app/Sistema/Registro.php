<?php

namespace App\Sistema;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Base\BaseBotoes;
use App\Sistema\Base\BaseController;
use App\Sistema\Base\BaseResource;
use App\Sistema\Enums\Banco;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Localiza módulos, tabelas e as classes geradas (model, view,
 * gerenciamento, botões) a partir dos nomes do dicionário de dados.
 */
class Registro
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    public static function limpar(): void
    {
        static::$cache = [];
    }

    /**
     * Pelo prefixo (op), nome (Operacional) ou pasta do módulo.
     */
    public static function modulo(string $modulo): Modulo
    {
        return static::$cache['modulo:' . mb_strtolower($modulo)] ??= Modulo::query()
            ->whereRaw('lower(prefixo) = ?', [mb_strtolower($modulo)])
            ->orWhereRaw('lower(nome) = ?', [mb_strtolower($modulo)])
            ->orWhereRaw('lower(pasta) = ?', [mb_strtolower($modulo)])
            ->first() ?? throw new RuntimeException("Módulo \"{$modulo}\" não encontrado.");
    }

    /**
     * Tabela do módulo pelo nome físico, com ou sem prefixo (op_usuario ou usuario) ou pela classe (OpUsuario).
     */
    public static function tabela(string $modulo, string $nome): MdTabela
    {
        $modulo = static::modulo($modulo);
        $chave = "tabela:{$modulo->id}:" . mb_strtolower($nome);

        return static::$cache[$chave] ??= $modulo->tabelas()->with('modulo')->get()->first(
            // sem diferenciar maiúscula: OP_PRODUTO, op_produto, produto ou ModelProduto
            fn (MdTabela $tabela) => in_array(mb_strtolower($nome), array_map('mb_strtolower', [
                $tabela->nome_fisico,
                $tabela->nomeSemPrefixo(),
                $tabela->classeModel(),
            ]), true)
        ) ?? throw new RuntimeException("Tabela \"{$nome}\" não encontrada no módulo {$modulo->nome}.");
    }

    public static function tabelaPorNomeFisico(string $nomeFisico): MdTabela
    {
        return static::$cache["tabela-nf:{$nomeFisico}"] ??= MdTabela::with('modulo')->where('nome_fisico', mb_strtoupper($nomeFisico))->first()
            ?? throw new RuntimeException("Tabela \"{$nomeFisico}\" não está no dicionário.");
    }

    public static function gerenciamento(string $nomeFisico): MdGerenciamento
    {
        return static::$cache["gerenciamento:{$nomeFisico}"] ??= MdGerenciamento::query()
            ->with(['tabela.modulo', 'tabela.campos.fkTabela', 'tabela.situacoes.status', 'formulario.botoes', 'botoes'])
            ->where('nome_fisico', $nomeFisico)
            ->first() ?? throw new RuntimeException("Gerenciamento \"{$nomeFisico}\" não encontrado.");
    }

    public static function model(MdTabela $tabela): Model
    {
        $class = $tabela->modelFqcn();

        if (! class_exists($class)) {
            throw new RuntimeException("O model {$class} não existe. Salve a tabela \"{$tabela->nome}\" no dicionário para gerá-lo.");
        }

        return new $class;
    }

    /**
     * Classe do Resource do gerenciamento (null se o arquivo ainda não foi gerado).
     *
     * @return class-string<BaseResource>|null
     */
    public static function resource(MdGerenciamento $gerenciamento): ?string
    {
        $class = $gerenciamento->resourceFqcn();

        // sem as páginas (arquivo apagado à mão) o painel nem sobe: ignora até gerar de novo
        return class_exists($class) && is_subclass_of($class, BaseResource::class) && class_exists($class . '\\Pages\\Listar')
            ? $class
            : null;
    }

    /**
     * Tela de um formulário (para os ganchos de personalização): a do Resource
     * do gerenciamento que usa o formulário; sem Resource, a base.
     */
    public static function tela(MdFormulario $formulario, string $operacao = BaseResource::INCLUIR, ?Model $registro = null): BaseResource
    {
        $gerenciamento = MdGerenciamento::with('tabela.modulo')
            ->where('md_formulario_id', $formulario->id)
            ->orderBy('id')
            ->first()
            ?? MdGerenciamento::with('tabela.modulo')->where('md_tabela_id', $formulario->md_tabela_id)->orderBy('id')->first();

        $class = $gerenciamento ? static::resource($gerenciamento) : null;

        if ($class) {
            return $class::tela($operacao, $registro);
        }

        $tela = new class extends BaseResource {};
        $tela->operacao = $operacao;
        $tela->registro = $registro;

        return $tela;
    }

    /**
     * Resources dos gerenciamentos de um banco, para registrar no painel.
     *
     * @return list<class-string<BaseResource>>
     */
    public static function resources(Banco $banco): array
    {
        try {
            if (! Schema::connection('central')->hasTable('md_gerenciamentos')) {
                return [];
            }

            return MdGerenciamento::with('tabela.modulo')
                ->whereHas('tabela', fn ($query) => $query->where('banco', $banco->value)->whereNotNull('generated_at'))
                ->get()
                ->map(fn (MdGerenciamento $gerenciamento) => static::resource($gerenciamento))
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return []; // banco fora do ar, instalação nova
        }
    }

    /**
     * Controller de regras de negócio da tabela; sem o arquivo, usa a base.
     */
    public static function controller(MdTabela $tabela): BaseController
    {
        $class = $tabela->controllerFqcn();

        return class_exists($class) ? app($class) : new class extends BaseController {};
    }

    /**
     * Classe de botões (nome_arquivo do botão; padrão: BotoesProduto); sem o arquivo, usa a base.
     */
    public static function botoes(MdTabela $tabela, ?string $classe = null): BaseBotoes
    {
        $class = $tabela->classeFqcn($classe ?: $tabela->classeBotoesPadrao());

        return class_exists($class)
            ? new $class($tabela)
            : new class($tabela) extends BaseBotoes {};
    }

    // ------------------------------------------------------------------
    // Busca por módulo + nome (getView, getGerenciamento, getBotoes)
    // ------------------------------------------------------------------

    /**
     * Pelo nome do arquivo (ViewProduto), nome físico do formulário ou nome da tabela (formulário padrão).
     */
    public static function formularioDe(string $modulo, string $nome): MdFormulario
    {
        $modulo = static::modulo($modulo);

        $formulario = MdFormulario::query()
            ->whereHas('tabela', fn ($q) => $q->where('modulo_id', $modulo->id))
            ->where(fn ($q) => $q->where('nome_arquivo', $nome)->orWhere('nome_fisico', $nome))
            ->first();

        if (! $formulario) {
            $formulario = static::tabela($modulo->prefixo, $nome)->formularioPadrao();
        }

        return $formulario ?? throw new RuntimeException("Formulário \"{$nome}\" não encontrado no módulo {$modulo->nome}.");
    }

    /**
     * Pelo nome do arquivo (GerenciamentoProduto), nome físico do gerenciamento ou nome da tabela (o primeiro dela).
     */
    public static function gerenciamentoDe(string $modulo, string $nome): MdGerenciamento
    {
        $modulo = static::modulo($modulo);

        $gerenciamento = MdGerenciamento::query()
            ->whereHas('tabela', fn ($q) => $q->where('modulo_id', $modulo->id))
            ->where(fn ($q) => $q->where('nome_arquivo', $nome)->orWhere('nome_fisico', $nome))
            ->first();

        if (! $gerenciamento) {
            $gerenciamento = static::tabela($modulo->prefixo, $nome)->gerenciamentos()->orderBy('id')->first();
        }

        return $gerenciamento ?? throw new RuntimeException("Gerenciamento \"{$nome}\" não encontrado no módulo {$modulo->nome}.");
    }

    /**
     * Pelo nome do arquivo (BotoesProduto) ou pelo nome da tabela (classe padrão).
     */
    public static function botoesDe(string $modulo, string $nome): BaseBotoes
    {
        $modulo = static::modulo($modulo);

        foreach ($modulo->tabelas as $tabela) {
            if (class_exists($tabela->classeFqcn($nome)) && is_subclass_of($tabela->classeFqcn($nome), BaseBotoes::class)) {
                return static::botoes($tabela, $nome);
            }
        }

        return static::botoes(static::tabela($modulo->prefixo, $nome));
    }
}
