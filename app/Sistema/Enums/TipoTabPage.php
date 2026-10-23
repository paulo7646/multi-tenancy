<?php

namespace App\Sistema\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TipoTabPage: string implements HasDescription, HasIcon, HasLabel
{
    case Campos = 'campos';
    case Pai = 'pai';
    case Tabela = 'tabela';
    case Customizada = 'customizada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Campos => 'Campos da tabela',
            self::Pai => 'Campos da tabela pai',
            self::Tabela => 'Tabela',
            self::Customizada => 'Customizada',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Campos => 'Campos da tabela do formulário.',
            self::Pai => 'Campos da tabela apontada por uma FK, só para consulta.',
            self::Tabela => 'Lista de outra tabela, ligada por campos, com incluir, alterar e excluir.',
            self::Customizada => 'Conteúdo feito por código, no Resource da tela.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Campos => 'heroicon-o-rectangle-group',
            self::Pai => 'heroicon-o-arrow-up-on-square',
            self::Tabela => 'heroicon-o-table-cells',
            self::Customizada => 'heroicon-o-code-bracket',
        };
    }

    /** Tem componentes cadastrados (campos e pai) */
    public function temComponentes(): bool
    {
        return $this === self::Campos || $this === self::Pai;
    }
}
