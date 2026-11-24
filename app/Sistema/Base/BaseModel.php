<?php

namespace App\Sistema\Base;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Base\Concerns\Principal;
use App\Sistema\Base\Concerns\Utilidades;
use App\Sistema\Registro;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Base dos models gerados pelo dicionário de dados.
 *
 * O model só guarda os dados e sabe se carregar do banco; as regras (beforePost, afterInsert...) ficam
 * no controller da tabela, chamado pelos formulários e pelo VQueryTabela. Gravar direto com
 * $model->save() NÃO passa por elas.
 *
 *   $produto = $this->getModel('op', 'produto');
 *   $produto->set('CODIGO', 'A-10')->persiste();       // carrega pelo campo chave
 *   $produto->set('CODIGO', 'A-10')->persiste(true);    // e todos os filhos, netos... pela FK
 */
abstract class BaseModel extends Model
{
    use Principal;
    use Utilidades {
        Utilidades::getModel as protected getModelDoModulo;
    }

    /** Profundidade máxima de filhos carregados (proteção contra dados circulares) */
    protected const PROFUNDIDADE_MAXIMA = 50;

    /**
     * $this->getModel('op', 'produto'): model de qualquer módulo (como nas outras classes).
     * Sem argumentos (ModelProduto::getModel()): o próprio model, como o
     * Eloquent faz pelo query builder (usado pelo Shield, entre outros).
     */
    public static function getModel(?string $modulo = null, ?string $nome = null): Model
    {
        return $modulo === null
            ? new static
            : Registro::model(Registro::tabela($modulo, (string) $nome));
    }

    /** Nome físico da tabela no dicionário (md_tabelas) */
    protected static string $mdTabela = '';

    public static function mdTabela(): string
    {
        return static::$mdTabela;
    }

    protected function conexaoPrincipal(): ConnectionInterface
    {
        return $this->getConnection();
    }

    // ------------------------------------------------------------------
    // Chave de busca e carga do registro
    // ------------------------------------------------------------------

    /**
     * Coluna usada para localizar o registro: o campo marcado como "chave" no dicionário;
     * sem ele, a chave primária (id).
     */
    public function nomeDaChave(): string
    {
        $chave = Registro::tabelaPorNomeFisico(static::$mdTabela)->campos->firstWhere('chave', true);

        return $chave?->nome_fisico ?? $this->getKeyName();
    }

    /**
     * $produto->set('CODIGO', 'A-10')->persiste()
     */
    public function set(string $campo, mixed $valor): static
    {
        $this->setAttribute($campo, $valor);

        return $this;
    }

    /**
     * Preenche o model com os dados do registro cujo campo chave (veja nomeDaChave()) já foi informado.
     *
     * Com $bPersisteFilhos, carrega também as tabelas filhas (as que têm FK para esta), depois as
     * filhas delas, e assim por diante até o fim. Cada filha vira um array de models com o nome
     * físico dela: $produto->OP_PRECO[0]->valor.
     */
    public function persiste(bool $bPersisteFilhos = false): static
    {
        return $this->persistePor($this->nomeDaChave(), $bPersisteFilhos);
    }

    /**
     * Como persiste(), mas localizando por outra coluna já preenchida (ex.: a chave primária).
     */
    public function persistePor(string $chave, bool $bPersisteFilhos = false): static
    {
        $valor = $this->getAttribute($chave);

        if ($valor === null) {
            throw new RuntimeException("Informe o campo chave ({$chave}) com set() antes de chamar persiste().");
        }

        $registro = $this->newQuery()->where($chave, $valor)->first()
            ?? throw (new ModelNotFoundException)->setModel(static::class, [$valor]);

        $this->setRawAttributes($registro->getAttributes(), true);
        $this->exists = true;
        $this->wasRecentlyCreated = false;
        $this->setRelations([]);

        if ($bPersisteFilhos) {
            $this->carregarFilhos(new Collection([$this]));
        }

        return $this;
    }

    /**
     * Tabelas filhas: nome físico => [tabela, coluna da FK que aponta para esta].
     *
     * @return array<string, array{0: MdTabela, 1: string}>
     */
    public function tabelasFilhas(): array
    {
        $tabela = Registro::tabelaPorNomeFisico(static::$mdTabela);

        return MdCampo::query()
            ->with('tabela.modulo')
            ->where('fk_tabela_id', $tabela->id)
            ->orderBy('id')
            ->get()
            ->unique('md_tabela_id')
            ->mapWithKeys(fn (MdCampo $campo) => [$campo->tabela->nome_fisico => [$campo->tabela, $campo->nome_fisico]])
            ->all();
    }

    /**
     * Carrega as tabelas filhas (só um nível) no registro já carregado.
     *
     * @param  list<string>|null  $nomes  null = todas as filhas
     */
    public function preencherFilhos(?array $nomes = null): void
    {
        $this->carregarFilhos(new Collection([$this]), 0, $nomes, false);
    }

    /**
     * Carrega os filhos de vários registros da MESMA tabela com uma consulta por tabela filha e
     * desce para os filhos deles do mesmo jeito.
     *
     * @param  Collection<int, static>  $pais
     * @param  list<string>|null  $nomes  null = todas as filhas
     */
    protected function carregarFilhos(Collection $pais, int $nivel = 0, ?array $nomes = null, bool $descer = true): void
    {
        if ($nivel >= static::PROFUNDIDADE_MAXIMA || $pais->isEmpty()) {
            return;
        }

        $filhas = $this->tabelasFilhas();
        $nomes = $nomes === null ? null : array_map('mb_strtoupper', $nomes);

        if ($nomes !== null && ($desconhecidas = array_diff($nomes, array_keys($filhas))) !== []) {
            throw new RuntimeException('Não são filhas de '.static::$mdTabela.': '.implode(', ', $desconhecidas)
                .'. Filhas: '.(implode(', ', array_keys($filhas)) ?: 'nenhuma').'.');
        }

        $chaves = $pais->map(fn (Model $pai) => $pai->getKey())->all();

        foreach ($filhas as $nome => [$tabela, $coluna]) {
            if ($nomes !== null && ! in_array($nome, $nomes, true)) {
                continue;
            }

            $modelo = Registro::model($tabela);
            $filhos = $modelo->newQuery()->whereIn($coluna, $chaves)->orderBy($modelo->getKeyName())->get();
            $porPai = $filhos->groupBy($coluna);

            foreach ($pais as $pai) {
                $pai->setRelation($nome, $porPai->get($pai->getKey(), new Collection)->values()->all());
            }

            if ($descer && $filhos->isNotEmpty() && $modelo instanceof self) {
                $modelo->carregarFilhos($filhos, $nivel + 1);
            }
        }
    }
}
