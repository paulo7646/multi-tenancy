<?php

namespace App\Sistema\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipo da tabela do dicionário.
 *  - Cadastro: tabela normal.
 *  - Ligação: igual ao cadastro, mas sem formulário/tela; liga duas tabelas
 *    (FK para cada uma) e aparece nas tab pages do tipo Tabela.
 *  - Lista: cada item (NOME em maiúscula) vira uma constante de enum no módulo,
 *    com VALOR inteiro sequencial definido pelo sistema (1, 2, 3...).
 *  - Conteúdo: igual, com o VALOR definido pelo usuário.
 * Os itens de lista e conteúdo são cadastrados no botão Valores da lista; uma migration
 * grava os registros na tabela (igual em todos os bancos).
 */
enum TipoTabela: string implements HasLabel, HasDescription, HasIcon
{
    case Cadastro = 'cadastro';
    case Ligacao = 'ligacao';
    case Lista = 'lista';
    case Conteudo = 'conteudo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cadastro => 'Cadastro',
            self::Ligacao => 'Tabela de ligação',
            self::Lista => 'Lista',
            self::Conteudo => 'Conteúdo',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Cadastro => 'Tabela normal.',
            self::Ligacao => 'Liga duas tabelas (ex.: PRODUTO_PEDIDO, com FK para cada uma). Não tem formulário nem tela: numa tab page do tipo Tabela, mostra os registros do outro lado da ligação.',
            self::Lista => 'Itens fixos com NOME e VALOR inteiro sequencial (1, 2, 3...) definido pelo sistema: cada NOME vira uma constante do enum do módulo.',
            self::Conteudo => 'Itens fixos com NOME e o VALOR que você definir: cada NOME vira uma constante do enum do módulo.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Cadastro => 'heroicon-o-table-cells',
            self::Ligacao => 'heroicon-o-link',
            self::Lista => 'heroicon-o-list-bullet',
            self::Conteudo => 'heroicon-o-tag',
        };
    }

    public function temItens(): bool
    {
        return $this === self::Lista || $this === self::Conteudo;
    }

    /** Registros gravados pelos usuários (cadastro e ligação); lista e conteúdo vêm do dicionário */
    public function editavel(): bool
    {
        return ! $this->temItens();
    }

    /** Cria formulário e tela (a de ligação, não) */
    public function temTelas(): bool
    {
        return $this !== self::Ligacao;
    }

    /**
     * Coluna que identifica o item (e é o valor do enum).
     */
    public function colunaChave(): ?string
    {
        return match ($this) {
            self::Lista, self::Conteudo => 'VALOR',
            self::Cadastro, self::Ligacao => null,
        };
    }

    /**
     * Campos criados pelo sistema na tabela.
     *
     * @return list<array<string, mixed>>
     */
    public function camposDoSistema(): array
    {
        return match ($this) {
            self::Lista => [
                ['nome' => 'Nome', 'nome_fisico' => 'NOME', 'tipo' => TipoCampo::String->value, 'tamanho' => 60, 'obrigatorio' => true, 'unico' => true],
                ['nome' => 'Valor', 'nome_fisico' => 'VALOR', 'tipo' => TipoCampo::UnsignedInteger->value, 'obrigatorio' => true, 'unico' => true],
            ],
            self::Conteudo => [
                ['nome' => 'Nome', 'nome_fisico' => 'NOME', 'tipo' => TipoCampo::String->value, 'tamanho' => 60, 'obrigatorio' => true, 'unico' => true],
                ['nome' => 'Valor', 'nome_fisico' => 'VALOR', 'tipo' => TipoCampo::String->value, 'tamanho' => 60, 'obrigatorio' => true, 'unico' => true],
            ],
            self::Cadastro, self::Ligacao => [],
        };
    }
}
