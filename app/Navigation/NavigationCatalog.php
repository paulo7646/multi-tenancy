<?php

namespace App\Navigation;

use App\Sistema\Runtime\ModuloNavegacao;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Lista as páginas e resources que aparecem no menu de cada painel,
 * com os valores padrão definidos no código.
 */
class NavigationCatalog
{
    public const PANELS = [
        'admin' => 'Painel central',
        'client' => 'Painel das empresas',
    ];

    /**
     * @return array<string, array{label: string, group: ?string, icon: ?string, sort: int}> chave = classe (ou chave do item)
     */
    public static function items(string $panelId): array
    {
        $panel = Filament::getPanel($panelId);
        $previous = Filament::getCurrentPanel();

        // Alguns plugins leem a própria configuração do painel atual
        Filament::setCurrentPanel($panel);

        try {
            $items = [];

            foreach ([...$panel->getPages(), ...$panel->getResources()] as $class) {
                if (filled($class::getCluster()) || ! $class::shouldRegisterNavigation()) {
                    continue;
                }
                if (is_subclass_of($class, Resource::class) && (! $class::hasPage('index') || $class::getParentResourceRegistration())) {
                    continue;
                }

                $items[$class] = [
                    'label' => static::text($class::getNavigationLabel()),
                    'group' => static::groupName($class::getNavigationGroup()),
                    'icon' => static::iconName($class::getNavigationIcon()),
                    'sort' => $class::getNavigationSort() ?? 0,
                ];
            }

            return [...collect($items)->sortBy('sort')->all(), ...ModuloNavegacao::catalogo($panelId)];
        } finally {
            Filament::setCurrentPanel($previous);
        }
    }

    /**
     * Opções de ícone (Heroicons contornados), no formato "heroicon-o-home".
     *
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return collect(Heroicon::cases())
            ->filter(fn (Heroicon $icon) => str_starts_with($icon->name, 'Outlined'))
            ->mapWithKeys(fn (Heroicon $icon) => ['heroicon-' . $icon->value => substr($icon->value, 2)])
            ->all();
    }

    /**
     * Resultado da busca do seletor de ícones, com a prévia do desenho.
     *
     * @return array<string, string>
     */
    public static function searchIcons(string $search): array
    {
        return collect(static::iconOptions())
            ->filter(fn (string $name) => str_contains($name, strtolower($search)))
            ->take(50)
            ->mapWithKeys(fn (string $name, string $icon) => [$icon => static::iconLabel($icon)])
            ->all();
    }

    /**
     * Rótulo HTML de um ícone (desenho + nome), para selects com allowHtml().
     */
    public static function iconLabel(string $icon): string
    {
        return '<span style="display:inline-flex;align-items:center;gap:.5rem">'
            . svg($icon, '', ['style' => 'width:1.25rem;height:1.25rem'])->toHtml()
            . e(str_replace('heroicon-o-', '', $icon))
            . '</span>';
    }

    public static function groupName(string|UnitEnum|null $group): ?string
    {
        return match (true) {
            $group instanceof HasLabel => static::text($group->getLabel()),
            $group instanceof UnitEnum => $group->name,
            default => $group,
        };
    }

    public static function iconName(string|BackedEnum|Htmlable|null $icon): ?string
    {
        return match (true) {
            $icon instanceof Heroicon => 'heroicon-' . $icon->value,
            $icon instanceof BackedEnum => (string) $icon->value,
            $icon instanceof Htmlable => null,
            default => $icon,
        };
    }

    protected static function text(string|Htmlable|null $value): string
    {
        return $value instanceof Htmlable ? strip_tags($value->toHtml()) : (string) $value;
    }
}
