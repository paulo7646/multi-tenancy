<?php

namespace App\Sistema\Base\Pages;

use App\Sistema\Base\BaseResource;
use App\Sistema\Registro;
use App\Sistema\Runtime\Executor;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Gravação das telas de incluir/alterar: antesDeSalvar, save (eventos do
 * model) e depoisDeSalvar numa transação; $this->abortar() ou um evento que
 * cancela mostram a mensagem e mantêm a tela como está.
 */
trait GravaNoDicionario
{
    protected function gravar(Model $registro, array $dados, string $operacao): Model
    {
        /** @var class-string<BaseResource> $resource */
        $resource = static::getResource();
        $tela = $resource::tela($operacao, $registro->exists ? $registro : null);

        // as regras da tabela (beforePost, afterInsert, afterAfter...) estão no controller dela
        $controller = Registro::controller($resource::md()->tabela);

        $salvo = Executor::transacao($registro, function () use ($tela, $controller, $registro, $dados) {
            $dados = $tela->antesDeSalvar($dados);

            $registro->exists ? $controller->alterar($registro, $dados) : $controller->incluir($registro, $dados);

            $tela->depoisDeSalvar($registro);

            return true;
        });

        if (! $salvo) {
            // a mensagem já foi mostrada; desfaz a transação do Filament e fica na tela
            throw (new Halt)->rollBackDatabaseTransaction();
        }

        return $registro;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return static::getResource()::tela(BaseResource::operacaoDo($this->getOperacaoFilament()), $this->getRecord())->antesDePreencher($data);
    }

    abstract protected function getOperacaoFilament(): string;
}
