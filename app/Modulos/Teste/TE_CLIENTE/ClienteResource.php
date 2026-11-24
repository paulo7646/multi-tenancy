<?php

namespace App\Modulos\Teste\TE_CLIENTE;

use App\Sistema\Base\BaseResource;

/**
 * Tela "cliente" da tabela TE_CLIENTE (Resource do Filament).
 *
 * Formulário, colunas, filtros e botões vêm do dicionário de dados.
 * Permissões no Shield (Funções): view_any_te_cliente, create_te_cliente...
 * Sobrescreva os métodos de App\Sistema\Base\BaseResource para personalizar:
 *
 *   // formulário ($this->operacao: incluir, alterar, visualizar; $this->registro)
 *   public function getReadOnly(): bool
 *   public function getCamposSomenteLeitura(): array         // ['campo', ...]
 *   public function getCamposOcultos(): array
 *   public function personalizarCampo(string $nomeFisico, Component $componente): Component
 *   public function antesDePreencher(array $dados): array
 *   public function antesDeSalvar(array $dados): array      // pode chamar $this->abortar('...')
 *   public function depoisDeSalvar(Model $registro): void
 *
 *   // listagem
 *   public function query(Builder $query): Builder
 *   public function personalizarColuna(string $nomeFisico, Column $coluna): Column
 *   public function filtros(): array
 *
 *   // regras além da permissão do papel
 *   public function podeIncluir(): bool
 *   public function podeAlterar(Model $registro): bool
 *   public function podeExcluir(Model $registro): bool
 *   public function botaoVisivel(string $nomeFisico, ?Model $registro = null): bool
 */
class ClienteResource extends BaseResource
{
    protected static string $gerenciamento = 'TE_CLIENTE';
}
