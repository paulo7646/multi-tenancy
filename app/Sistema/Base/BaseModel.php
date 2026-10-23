<?php

namespace App\Sistema\Base;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Base\Concerns\Utilidades;
use App\Sistema\Registro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Base dos models gerados pelo dicionário de dados, com o controle de eventos.
 *
 * Ordem ao INCLUIR:  beforePost -> beforeInsert -> (insert) -> afterInsert -> afterPost
 * Ordem ao ALTERAR:  beforePost -> beforeUpdate -> (update) -> afterUpdate -> afterPost
 * Ordem ao EXCLUIR:  beforeDelete -> (delete) -> afterDelete
 *
 * Os "afterAfter" (afterAfterInsert, afterAfterUpdate, afterAfterPost,
 * afterAfterDelete) rodam depois do COMMIT da transação: bom lugar para
 * e-mails, filas e integrações, que não devem acontecer se a gravação for
 * desfeita. Sem transação aberta, rodam logo em seguida.
 *
 * Para cancelar a operação: chame $this->abortar('mensagem') (a tela mostra a
 * mensagem e a transação é desfeita) ou retorne false de um "before".
 *
 * Use $this->inserindo() para saber se o registro está sendo incluído e
 * $this->isDirty('campo') / $this->getOriginal('campo') para comparar valores.
 *
 * Se o model filho declarar booted(), chame parent::booted().
 */
abstract class BaseModel extends Model
{
    use Utilidades {
        Utilidades::getModel as protected getModelDoModulo;
    }

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

    protected static function booted(): void
    {
        static::saving(fn (self $model) => $model->beforePost());
        static::creating(fn (self $model) => $model->beforeInsert());
        static::updating(fn (self $model) => $model->beforeUpdate());

        static::created(function (self $model) {
            $model->afterInsert();
            $model->depoisDoCommit(fn () => $model->afterAfterInsert());
        });

        static::updated(function (self $model) {
            $model->afterUpdate();
            $model->depoisDoCommit(fn () => $model->afterAfterUpdate());
        });

        static::saved(function (self $model) {
            $model->afterPost();
            $model->depoisDoCommit(fn () => $model->afterAfterPost());
        });

        static::deleting(fn (self $model) => $model->beforeDelete());

        static::deleted(function (self $model) {
            $model->afterDelete();
            $model->depoisDoCommit(fn () => $model->afterAfterDelete());
        });
    }

    public static function mdTabela(): string
    {
        return static::$mdTabela;
    }

    // ------------------------------------------------------------------
    // Chave primária e carga do registro
    // ------------------------------------------------------------------

    /**
     * $this->getModel('op', 'produto')->setPrimaryKey(1)->refresh()
     */
    public function setPrimaryKey(mixed $valor): static
    {
        $this->setAttribute($this->getKeyName(), $valor);

        return $this;
    }

    public function getPrimaryKey(): mixed
    {
        return $this->getKey();
    }

    /**
     * Preenche o model com os dados do registro da chave primária.
     *
     * $preencheFilhos:
     *  - false (padrão): só o registro;
     *  - true: também as tabelas filhas (as que têm FK para esta);
     *  - ['op_preco', ...]: só essas filhas.
     *
     * Cada filha vira um array de models com o nome físico dela:
     * $produto->op_preco[0]->valor
     *
     * @param  bool|list<string>  $preencheFilhos
     */
    public function refresh(bool|array $preencheFilhos = false): static
    {
        $chave = $this->getKey();

        if ($chave === null) {
            throw new RuntimeException('Informe a chave com setPrimaryKey() antes de chamar refresh().');
        }

        $registro = $this->newQuery()->find($chave)
            ?? throw (new ModelNotFoundException)->setModel(static::class, [$chave]);

        $this->setRawAttributes($registro->getAttributes(), true);
        $this->exists = true;
        $this->wasRecentlyCreated = false;
        $this->setRelations([]);

        if ($preencheFilhos !== false) {
            $this->preencherFilhos($preencheFilhos === true ? null : $preencheFilhos);
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
     * Carrega as tabelas filhas no registro já carregado.
     *
     * @param  list<string>|null  $nomes  null = todas as filhas
     */
    public function preencherFilhos(?array $nomes = null): void
    {
        $filhas = $this->tabelasFilhas();

        // nomes físicos em maiúscula (aceita op_preco ou OP_PRECO)
        $nomes = $nomes === null ? null : array_map('mb_strtoupper', $nomes);

        if ($nomes !== null && ($desconhecidas = array_diff($nomes, array_keys($filhas))) !== []) {
            throw new RuntimeException('Não são filhas de ' . static::$mdTabela . ': ' . implode(', ', $desconhecidas)
                . '. Filhas: ' . (implode(', ', array_keys($filhas)) ?: 'nenhuma') . '.');
        }

        foreach ($filhas as $nome => [$tabela, $coluna]) {
            if ($nomes !== null && ! in_array($nome, $nomes, true)) {
                continue;
            }

            $this->setRelation($nome, Registro::model($tabela)->newQuery()
                ->where($coluna, $this->getKey())
                ->orderBy(Registro::model($tabela)->getKeyName())
                ->get()
                ->all());
        }
    }

    /**
     * Está incluindo (antes ou logo depois do insert)?
     */
    public function inserindo(): bool
    {
        return ! $this->exists || $this->wasRecentlyCreated;
    }

    protected function depoisDoCommit(callable $callback): void
    {
        $this->getConnection()->afterCommit($callback);
    }

    // ------------------------------------------------------------------
    // Eventos: sobrescreva no model da tabela
    // ------------------------------------------------------------------

    /** Antes de incluir ou alterar. Retorne false para cancelar. */
    protected function beforePost(): ?bool
    {
        return null;
    }

    /** Antes de incluir. Retorne false para cancelar. */
    protected function beforeInsert(): ?bool
    {
        return null;
    }

    /** Antes de alterar. Retorne false para cancelar. */
    protected function beforeUpdate(): ?bool
    {
        return null;
    }

    /** Antes de excluir. Retorne false para cancelar. */
    protected function beforeDelete(): ?bool
    {
        return null;
    }

    /** Depois de incluir (dentro da transação). */
    protected function afterInsert(): void {}

    /** Depois de alterar (dentro da transação). */
    protected function afterUpdate(): void {}

    /** Depois de incluir ou alterar (dentro da transação). */
    protected function afterPost(): void {}

    /** Depois de excluir (dentro da transação). */
    protected function afterDelete(): void {}

    /** Depois do commit de uma inclusão. */
    protected function afterAfterInsert(): void {}

    /** Depois do commit de uma alteração. */
    protected function afterAfterUpdate(): void {}

    /** Depois do commit de uma inclusão ou alteração. */
    protected function afterAfterPost(): void {}

    /** Depois do commit de uma exclusão. */
    protected function afterAfterDelete(): void {}
}
