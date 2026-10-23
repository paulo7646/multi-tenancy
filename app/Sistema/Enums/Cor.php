<?php

namespace App\Sistema\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cores para ícones de situação e botões. As cores do tema (primary,
 * success...) acompanham o painel; as demais são da paleta do Tailwind.
 */
enum Cor: string implements HasLabel, HasColor
{
    case Primary = 'primary';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';
    case Gray = 'gray';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Yellow = 'yellow';
    case Lime = 'lime';
    case Green = 'green';
    case Emerald = 'emerald';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Sky = 'sky';
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Purple = 'purple';
    case Fuchsia = 'fuchsia';
    case Pink = 'pink';
    case Rose = 'rose';
    case Slate = 'slate';

    public function getLabel(): string
    {
        return match ($this) {
            self::Primary => 'Principal (tema)',
            self::Success => 'Sucesso (tema)',
            self::Warning => 'Alerta (tema)',
            self::Danger => 'Perigo (tema)',
            self::Info => 'Informação (tema)',
            self::Gray => 'Cinza',
            self::Red => 'Vermelho',
            self::Orange => 'Laranja',
            self::Amber => 'Âmbar',
            self::Yellow => 'Amarelo',
            self::Lime => 'Lima',
            self::Green => 'Verde',
            self::Emerald => 'Esmeralda',
            self::Teal => 'Verde-azulado',
            self::Cyan => 'Ciano',
            self::Sky => 'Céu',
            self::Blue => 'Azul',
            self::Indigo => 'Índigo',
            self::Violet => 'Violeta',
            self::Purple => 'Roxo',
            self::Fuchsia => 'Fúcsia',
            self::Pink => 'Rosa',
            self::Rose => 'Rosa-escuro',
            self::Slate => 'Ardósia',
        };
    }

    /**
     * Valor aceito por ->color() dos componentes do Filament.
     *
     * @return string|array<int, string>
     */
    public function getColor(): string|array
    {
        return match ($this) {
            self::Primary, self::Success, self::Warning, self::Danger, self::Info, self::Gray => $this->value,
            default => constant(Color::class . '::' . $this->name),
        };
    }

    public static function resolver(?string $cor): string|array
    {
        return (self::tryFrom((string) $cor) ?? self::Gray)->getColor();
    }

    /**
     * Valor CSS do tom 500 (para colorir um ícone fora dos componentes do Filament).
     */
    public static function css(?string $cor): string
    {
        $tom = (self::tryFrom((string) $cor) ?? self::Gray)->getColor();

        return is_array($tom) ? ($tom[500] ?? reset($tom)) : "var(--{$tom}-500)";
    }
}
