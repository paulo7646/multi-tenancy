<?php

namespace App\Sistema\Base\Concerns;

use App\Models\Md\MdTabela;
use App\Models\Tenant;
use App\Models\User;
use App\Sistema\Base\BaseBotoes;
use App\Sistema\Base\BaseController;
use App\Sistema\Base\BaseResource;
use App\Sistema\Exceptions\EventoException;
use App\Sistema\ModelProperty;
use App\Sistema\Registro;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Utilidades comuns a models, resources, botões e controllers.
 */
trait Utilidades
{
    /**
     * Model de qualquer módulo: $this->getModel('op', 'usuario') ou
     * $this->getModel('Operacional', 'op_usuario'). Aceita o prefixo, o nome
     * ou a pasta do módulo, e o nome físico da tabela com ou sem prefixo.
     *
     * @return Model nova instância (use ->newQuery(), ::where(...), ->create(...))
     */
    public function getModel(string $modulo, string $nome): Model
    {
        return Registro::model(Registro::tabela($modulo, $nome));
    }

    /**
     * Resource (tela) de qualquer módulo: $this->getResource('op', 'ProdutoResource') ou ('op', 'produto').
     * Para chamar a personalização dele: $this->getResource('op', 'produto')::tela('alterar', $registro).
     *
     * @return class-string<BaseResource>
     */
    public function getResource(string $modulo, string $nome): string
    {
        $gerenciamento = Registro::gerenciamentoDe($modulo, $nome);

        return Registro::resource($gerenciamento)
            ?? throw new \RuntimeException("O Resource {$gerenciamento->resourceFqcn()} não existe. Salve o gerenciamento no dicionário para gerá-lo.");
    }

    /**
     * Botões de qualquer módulo: $this->getBotoes('op', 'BotoesProduto') ou ('op', 'produto').
     */
    public function getBotoes(string $modulo, string $nome): BaseBotoes
    {
        return Registro::botoesDe($modulo, $nome);
    }

    /**
     * Controller de regras de negócio de qualquer tabela: $this->getController('op', 'produto').
     */
    public function getController(string $modulo, string $nome): BaseController
    {
        return Registro::controller(Registro::tabela($modulo, $nome));
    }

    /**
     * Registro de qualquer tabela: $this->modelProperty('op', 'produto', 1).
     */
    public function modelProperty(string $modulo, string $nome, mixed $id = null): ModelProperty
    {
        return new ModelProperty($modulo, $nome, $id);
    }

    /**
     * Enum de uma tabela lista/conteúdo de qualquer módulo: $this->getEnum('op', 'status')::ABERTO.
     *
     * @return class-string<\BackedEnum>
     */
    public function getEnum(string $modulo, string $nome): string
    {
        $tabela = Registro::tabela($modulo, $nome);

        if (! enum_exists($tabela->enumFqcn())) {
            throw new \RuntimeException("A tabela {$tabela->nome_fisico} não tem enum (só as do tipo lista ou conteúdo, depois de salvas).");
        }

        return $tabela->enumFqcn();
    }

    /**
     * Definição (md_tabelas) de uma tabela de qualquer módulo.
     */
    public function getTabela(string $modulo, string $nome): MdTabela
    {
        return Registro::tabela($modulo, $nome);
    }

    /**
     * Interrompe a operação mostrando a mensagem ao usuário (a transação é desfeita).
     */
    public function abortar(string $mensagem, ?string $titulo = null): never
    {
        throw new EventoException($mensagem, $titulo);
    }

    /**
     * Mostra uma notificação na tela. Tipos: success, danger, warning, info.
     */
    public function notificar(string $titulo, ?string $mensagem = null, string $tipo = 'success'): void
    {
        Notification::make()->title($titulo)->body($mensagem)->status($tipo)->send();
    }

    public function usuario(): ?User
    {
        return auth()->user();
    }

    /**
     * Cliente (empresa) atual, ou null na base pai.
     */
    public function tenantAtual(): ?Tenant
    {
        return tenant();
    }
}
