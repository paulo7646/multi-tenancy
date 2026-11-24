<?php

namespace App\Sistema\Base\Concerns;

use App\Sistema\Exceptions\EventoException;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Conexão com o banco e transações, comuns a quem opera registros: BaseModel, BaseController e VQueryTabela.
 * (PHP não deixa um Model e um Controller terem a mesma classe pai, então a "Principal" é uma trait.)
 *
 * transacao(): uma operação em transação (erro = rollback). Dentro de iniciaTransacao() ela vira um
 * savepoint, então várias operações podem ser agrupadas:
 *   iniciaTransacao() ... transacaoCommit() / transacaoRollBack(), ou finalizaTransacao()
 * (commit se tudo deu certo, rollback se alguma operação falhou).
 */
trait Principal
{
    /** Transações abertas por iniciaTransacao() neste objeto */
    protected int $transacoesAbertas = 0;

    /** Alguma operação falhou dentro da transação aberta? */
    protected ?Throwable $erro = null;

    /**
     * Conexão onde este objeto opera (a da tabela: central ou a do cliente).
     */
    abstract protected function conexaoPrincipal(): ConnectionInterface;

    public function iniciaTransacao(): static
    {
        $this->conexaoPrincipal()->beginTransaction();
        $this->transacoesAbertas++;
        $this->erro = null;

        return $this;
    }

    public function transacaoCommit(): static
    {
        $this->exigirTransacao('transacaoCommit');
        $this->conexaoPrincipal()->commit();
        $this->transacoesAbertas--;

        return $this;
    }

    /**
     * Desfaz tudo desde o iniciaTransacao().
     */
    public function transacaoRollBack(): static
    {
        $this->exigirTransacao('transacaoRollBack');
        $this->conexaoPrincipal()->rollBack();
        $this->transacoesAbertas--;

        return $this;
    }

    /**
     * Encerra as transações abertas por este objeto: commit se tudo deu certo, rollback se alguma
     * operação falhou. Retorna true no commit.
     */
    public function finalizaTransacao(): bool
    {
        $commit = $this->erro === null;

        while ($this->transacoesAbertas > 0) {
            $commit ? $this->transacaoCommit() : $this->transacaoRollBack();
        }

        $this->erro = null;

        return $commit;
    }

    public function emTransacao(): bool
    {
        return $this->transacoesAbertas > 0;
    }

    /**
     * Erro da última operação que falhou dentro da transação aberta.
     */
    public function getErro(): ?Throwable
    {
        return $this->erro;
    }

    /**
     * Roda a operação em transação (erro = rollback e a exceção sobe). Dentro de iniciaTransacao() vira
     * um savepoint.
     */
    public function transacao(Closure $operacao): mixed
    {
        try {
            return $this->conexaoPrincipal()->transaction($operacao);
        } catch (Throwable $e) {
            $this->erro = $e;

            throw $e;
        }
    }

    /**
     * Roda depois do COMMIT da transação em andamento (ou na hora, se não há transação): e-mails, filas
     * e integrações, que não devem acontecer se a gravação for desfeita.
     */
    public function depoisDoCommit(callable $callback): void
    {
        $this->conexaoPrincipal()->afterCommit($callback);
    }

    /**
     * Transação esquecida aberta é desfeita (nada fica pendurado na conexão).
     */
    protected function desfazerTransacoesEsquecidas(): void
    {
        while ($this->transacoesAbertas > 0) {
            try {
                $this->conexaoPrincipal()->rollBack();
            } catch (Throwable) {
                // conexão já fechada: nada a desfazer
            }

            $this->transacoesAbertas--;
        }
    }

    protected function exigirTransacao(string $metodo): void
    {
        if ($this->transacoesAbertas === 0) {
            throw new EventoException("{$metodo}(): não há transação aberta. Chame iniciaTransacao() antes.");
        }
    }
}
