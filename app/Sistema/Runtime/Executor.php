<?php

namespace App\Sistema\Runtime;

use App\Sistema\Base\BaseBotoes;
use App\Sistema\Exceptions\EventoException;
use App\Sistema\Geradores\Gerador;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Livewire\Component;

/**
 * Executa gravações e botões numa transação: eventos do model (beforePost,
 * afterPost...) e $this->abortar('...') desfazem tudo e viram mensagem na tela.
 */
class Executor
{
    /**
     * Roda o callback na transação da conexão do model. Retorna null se foi interrompido.
     */
    public static function transacao(Model $model, Closure $callback): mixed
    {
        try {
            return $model->getConnection()->transaction($callback);
        } catch (EventoException $e) {
            Notification::make()
                ->title($e->titulo ?? 'Operação não realizada')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (QueryException $e) {
            Notification::make()
                ->title('Operação não realizada')
                ->body(Gerador::erroAmigavel($e))
                ->danger()
                ->persistent()
                ->send();
        }

        return null;
    }

    /**
     * Executa o método de um botão (classe de botões da tabela).
     */
    public static function botao(BaseBotoes $botoes, Model $model, string $metodo, string $titulo, Component $tela, array $argumentos = []): void
    {
        if (! method_exists($botoes, $metodo)) {
            Notification::make()
                ->title("{$titulo}: método {$metodo}() não existe em " . get_class($botoes))
                ->body('Salve o formulário/gerenciamento no dicionário para gerar o método.')
                ->warning()
                ->send();

            return;
        }

        $botoes->tela = $tela;
        $interrompido = true;

        $retorno = static::transacao($model, function () use ($botoes, $metodo, $argumentos, &$interrompido) {
            $retorno = $botoes->{$metodo}(...$argumentos);
            $interrompido = false;

            return $retorno;
        });

        if ($interrompido) {
            return;
        }

        if (is_string($retorno) && $retorno !== '') {
            $tela->redirect($retorno);

            return;
        }

        if ($retorno !== false) {
            Notification::make()->title("{$titulo}: concluído")->success()->send();
        }
    }
}
