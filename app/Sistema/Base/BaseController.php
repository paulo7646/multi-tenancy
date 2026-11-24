<?php

namespace App\Sistema\Base;

use App\Http\Controllers\Controller;
use App\Models\Md\MdTabela;
use App\Sistema\Base\Concerns\Principal;
use App\Sistema\Base\Concerns\Utilidades;
use App\Sistema\Registro;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Base dos controllers das tabelas dos módulos: as regras e as ações da tabela.
 *
 * Quem grava (os formulários e o VQueryTabela) chama incluir(), alterar() e excluir(); eles rodam os
 * ganchos abaixo na ordem certa e o controller da tabela sobrescreve só os que precisa.
 *
 * INCLUIR:  beforeBeforePost -> [transação: beforePost -> beforeInsert -> (grava) -> afterInsert -> afterPost]
 *           -> COMMIT -> afterAfterInsert -> afterAfterPost
 * ALTERAR:  beforeBeforePost -> [transação: beforePost -> beforeUpdate -> (grava) -> afterUpdate -> afterPost]
 *           -> COMMIT -> afterAfterUpdate -> afterAfterPost
 * EXCLUIR:  beforeBeforeDelete -> [transação: beforeDelete -> (exclui) -> afterDelete]
 *           -> COMMIT -> afterAfterDelete
 *
 * "beforeBefore" roda antes de abrir a transação do controller (validações que não precisam do banco);
 * "afterAfter" roda depois do COMMIT (e-mails, filas e integrações: não acontecem se a gravação for
 * desfeita). Nos "before", o registro já vem preenchido com os dados novos: use $registro->campo,
 * $registro->isDirty('campo') e $registro->getOriginal('campo').
 *
 * Para cancelar: $this->abortar('mensagem') (a tela mostra a mensagem e a transação é desfeita) ou
 * retorne false de um "before".
 *
 * Também serve para usar as tabelas dos módulos ($this->getModel('op', 'usuario'), $this->abortar(...)).
 */
abstract class BaseController extends Controller
{
    use Principal, Utilidades;

    /** A tabela deste controller (definida por Registro::controller) */
    protected ?MdTabela $tabela = null;

    public function paraTabela(MdTabela $tabela): static
    {
        $this->tabela = $tabela;

        return $this;
    }

    protected function conexaoPrincipal(): ConnectionInterface
    {
        return Registro::model($this->tabela ?? throw new \RuntimeException('Controller sem tabela: obtenha-o por Registro::controller().'))->getConnection();
    }

    // ------------------------------------------------------------------
    // Ações: chamadas pelos formulários e pelo VQueryTabela
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $dados
     */
    public function incluir(Model $registro, array $dados = []): Model
    {
        if ($registro->exists) {
            $this->abortar('O registro já existe no banco: use alterar().');
        }

        $this->cancelarSe($this->beforeBeforePost($registro, $dados));

        $this->transacao(function () use ($registro, $dados) {
            $registro->fill($dados);

            $this->cancelarSe($this->beforePost($registro));
            $this->cancelarSe($this->beforeInsert($registro));
            $registro->save();
            $this->afterInsert($registro);
            $this->afterPost($registro);

            $this->depoisDoCommit(function () use ($registro) {
                $this->afterAfterInsert($registro);
                $this->afterAfterPost($registro);
            });
        });

        return $registro;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function alterar(Model $registro, array $dados = []): Model
    {
        if (! $registro->exists) {
            $this->abortar('Informe o registro a alterar (ele ainda não existe no banco).');
        }

        $this->cancelarSe($this->beforeBeforePost($registro, $dados));

        $this->transacao(function () use ($registro, $dados) {
            $registro->fill($dados);

            $this->cancelarSe($this->beforePost($registro));
            $this->cancelarSe($this->beforeUpdate($registro));
            $registro->save();
            $this->afterUpdate($registro);
            $this->afterPost($registro);

            $this->depoisDoCommit(function () use ($registro) {
                $this->afterAfterUpdate($registro);
                $this->afterAfterPost($registro);
            });
        });

        return $registro;
    }

    public function excluir(Model $registro): bool
    {
        if (! $registro->exists) {
            $this->abortar('Informe o registro a excluir (ele não existe no banco).');
        }

        $this->cancelarSe($this->beforeBeforeDelete($registro));

        $this->transacao(function () use ($registro) {
            $this->cancelarSe($this->beforeDelete($registro));
            $registro->delete();
            $this->afterDelete($registro);

            $this->depoisDoCommit(fn () => $this->afterAfterDelete($registro));
        });

        return true;
    }

    /**
     * Um "before" que devolve false cancela a operação.
     */
    protected function cancelarSe(?bool $retorno): void
    {
        if ($retorno === false) {
            $this->abortar('A operação foi cancelada pelas regras da tabela.');
        }
    }

    // ------------------------------------------------------------------
    // Ganchos: sobrescreva no controller da tabela
    // ------------------------------------------------------------------

    /**
     * Antes de abrir a transação de incluir ou alterar, com os dados que chegaram. Retorne false para cancelar.
     *
     * @param  array<string, mixed>  $dados
     */
    protected function beforeBeforePost(Model $registro, array $dados): ?bool
    {
        return null;
    }

    /** Antes de incluir ou alterar (registro já preenchido). Retorne false para cancelar. */
    protected function beforePost(Model $registro): ?bool
    {
        return null;
    }

    /** Antes de incluir. Retorne false para cancelar. */
    protected function beforeInsert(Model $registro): ?bool
    {
        return null;
    }

    /** Antes de alterar. Retorne false para cancelar. */
    protected function beforeUpdate(Model $registro): ?bool
    {
        return null;
    }

    /** Antes de abrir a transação de excluir. Retorne false para cancelar. */
    protected function beforeBeforeDelete(Model $registro): ?bool
    {
        return null;
    }

    /** Antes de excluir (dentro da transação). Retorne false para cancelar. */
    protected function beforeDelete(Model $registro): ?bool
    {
        return null;
    }

    /** Depois de incluir (dentro da transação). */
    protected function afterInsert(Model $registro): void {}

    /** Depois de alterar (dentro da transação). */
    protected function afterUpdate(Model $registro): void {}

    /** Depois de incluir ou alterar (dentro da transação). */
    protected function afterPost(Model $registro): void {}

    /** Depois de excluir (dentro da transação). */
    protected function afterDelete(Model $registro): void {}

    /** Depois do COMMIT de uma inclusão. */
    protected function afterAfterInsert(Model $registro): void {}

    /** Depois do COMMIT de uma alteração. */
    protected function afterAfterUpdate(Model $registro): void {}

    /** Depois do COMMIT de uma inclusão ou alteração. */
    protected function afterAfterPost(Model $registro): void {}

    /** Depois do COMMIT de uma exclusão. */
    protected function afterAfterDelete(Model $registro): void {}
}
