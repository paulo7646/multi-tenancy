<?php

namespace App\Navigation;

use App\Models\NavigationGroup as NavigationGroupSetting;
use App\Models\NavigationItem as NavigationItemSetting;
use Filament\Navigation\NavigationManager;

/**
 * Monta o menu do Filament normalmente (permissões, badges etc.) e aplica
 * por cima o que foi configurado na tela "Navegação": grupo, nome, ícone,
 * ordem e visibilidade de cada item, e a ordem dos grupos.
 */
class CustomNavigationManager extends NavigationManager
{
    protected bool $isCustomized = false;

    /** @var array<string>|null */
    protected ?array $groupOrder = null;

    public function get(): array
    {
        if ($this->panel->hasNavigationBuilder()) {
            return parent::get();
        }

        if (! $this->isNavigationMounted) {
            $this->mountNavigation();
        }

        if (! $this->isCustomized) {
            $this->applyItemSettings();
            $this->isCustomized = true;
        }

        return parent::get();
    }

    /**
     * Grupos salvos primeiro, na ordem definida; os demais em seguida.
     */
    public function getNavigationGroups(): array
    {
        $this->groupOrder ??= NavigationGroupSetting::where('panel', $this->panel->getId())
            ->orderBy('sort')
            ->pluck('name')
            ->all();

        $registered = parent::getNavigationGroups();

        return [
            ...$this->groupOrder,
            ...array_filter($registered, fn ($group) => ! in_array($group, $this->groupOrder, true)),
        ];
    }

    protected function applyItemSettings(): void
    {
        $settings = NavigationItemSetting::where('panel', $this->panel->getId())
            ->get()
            ->keyBy('item');

        if ($settings->isEmpty()) {
            return;
        }

        foreach ($this->navigationItems as $item) {
            // Resources e páginas usam a própria classe como chave do item
            $setting = $settings->get($item->getKey());

            if (! $setting) {
                continue;
            }

            $item->group($setting->group)->sort($setting->sort);

            if (filled($setting->label)) {
                $item->label($setting->label);
            }

            if (filled($setting->icon)) {
                $item->icon($setting->icon)->activeIcon($setting->icon);
            }

            if ($setting->hidden) {
                $item->visible(false);
            }
        }
    }
}
