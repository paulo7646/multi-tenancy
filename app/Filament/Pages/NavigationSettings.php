<?php

namespace App\Filament\Pages;

use App\Models\NavigationGroup;
use App\Models\NavigationItem;
use App\Navigation\NavigationCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Configura o menu lateral de cada painel, grupo por grupo: a ordem dos
 * grupos e, dentro de cada um, a ordem, o nome, o ícone e a visibilidade
 * dos itens. Os módulos do dicionário de dados entram como grupos.
 */
class NavigationSettings extends Page
{
    /** Valor do "Mover para" que leva o item para o topo (sem grupo) */
    protected const TOPO = '__topo';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static string | \UnitEnum | null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Navegação';

    protected static ?string $title = 'Navegação';

    protected static ?string $slug = 'navegacao';

    #[Url]
    public string $painel = 'admin';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    public function getSubheading(): string
    {
        return 'Arraste os grupos e os itens para ordenar; use "Mover para" para trocar um item de grupo. Ocultar um item só o tira do menu: quem tem permissão ainda abre a página pelo endereço.';
    }

    public function mount(): void
    {
        if (! array_key_exists($this->painel, NavigationCatalog::PANELS)) {
            $this->painel = 'admin';
        }

        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $catalog = NavigationCatalog::items($this->painel);
        $savedItems = NavigationItem::where('panel', $this->painel)->get()->keyBy('item');
        $savedGroups = NavigationGroup::where('panel', $this->painel)->orderBy('sort')->pluck('name');

        $items = collect($catalog)
            ->map(function (array $default, string $key) use ($savedItems) {
                $saved = $savedItems->get($key);

                return [
                    'item' => $key,
                    'group' => $saved ? $saved->group : $default['group'],
                    'label' => $saved?->label,
                    'icon' => $saved?->icon,
                    'hidden' => (bool) $saved?->hidden,
                    // itens novos (sem configuração) vão para o fim, na ordem do código
                    '_sort' => $saved ? $saved->sort : 100000 + $default['sort'],
                ];
            })
            ->sortBy('_sort');

        $groupNames = $savedGroups
            ->merge($items->pluck('group')->filter())
            ->unique()
            ->values();

        $semCampos = fn (array $item) => collect($item)->except(['group', '_sort'])->all();

        $this->form->fill([
            'topo' => static::comChaves($items->filter(fn ($i) => blank($i['group']))->map($semCampos)->values()->all()),
            'groups' => static::comChaves($groupNames->map(fn (string $name) => [
                'name' => $name,
                'items' => static::comChaves($items->where('group', $name)->map($semCampos)->values()->all()),
            ])->all()),
        ]);
    }

    /**
     * Chaves estáveis nos itens dos repeaters (o "Mover para" troca itens de lista).
     */
    protected static function comChaves(array $lista): array
    {
        return collect($lista)->mapWithKeys(fn (array $item) => [(string) Str::uuid() => $item])->all();
    }

    public function form(Schema $schema): Schema
    {
        $catalog = NavigationCatalog::items($this->painel);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Sem grupo (topo)')
                    ->description('Itens sem grupo ficam sempre no topo do menu.')
                    ->icon('heroicon-o-arrow-up')
                    ->collapsible()
                    ->schema([
                        static::itensRepeater('topo', $catalog, '../../'),
                    ]),

                Section::make('Grupos')
                    ->description('Arraste para ordenar os grupos. Grupos vazios não aparecem no menu.')
                    ->icon('heroicon-o-folder')
                    ->schema([
                        Repeater::make('groups')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome do grupo')
                                    ->required()
                                    ->distinct()
                                    ->live(onBlur: true),

                                static::itensRepeater('items', $catalog, '../../../../'),
                            ])
                            ->itemLabel(fn (array $state) => ($state['name'] ?? 'Novo grupo') . '  ·  ' . count($state['items'] ?? []) . ' ' . (count($state['items'] ?? []) === 1 ? 'item' : 'itens'))
                            ->addActionLabel('Novo grupo')
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->defaultItems(0),
                    ]),
            ]);
    }

    /**
     * Itens de uma lista (topo ou um grupo). $raiz leva do item à raiz do formulário.
     *
     * @param  array<string, array{label: string, group: ?string, icon: ?string, sort: int}>  $catalog
     */
    protected static function itensRepeater(string $nome, array $catalog, string $raiz): Repeater
    {
        return Repeater::make($nome)
            ->hiddenLabel()
            ->schema([
                Hidden::make('item'),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                    TextInput::make('label')
                        ->label('Nome')
                        ->placeholder(fn (Get $get) => $catalog[$get('item')]['label'] ?? null)
                        ->maxLength(100),

                    Select::make('icon')
                        ->label('Ícone')
                        ->placeholder(fn (Get $get) => static::iconPlaceholder($catalog[$get('item')]['icon'] ?? null))
                        ->searchable()
                        ->allowHtml()
                        ->getSearchResultsUsing(fn (string $search) => NavigationCatalog::searchIcons($search))
                        ->getOptionLabelUsing(fn (?string $value) => $value ? NavigationCatalog::iconLabel($value) : null),

                    Select::make('mover')
                        ->label('Mover para')
                        ->placeholder('Manter aqui')
                        ->options(fn (Get $get) => [
                            static::TOPO => 'Sem grupo (topo)',
                            ...collect($get("{$raiz}groups"))->pluck('name')->filter()->mapWithKeys(fn (string $n) => [$n => $n])->all(),
                        ])
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => static::mover($state, $get, $set, $raiz)),

                    Toggle::make('hidden')
                        ->label('Ocultar do menu')
                        ->inline(false)
                        // esta tela não pode sumir do menu
                        ->disabled(fn (Get $get) => $get('item') === static::class),
                ]),
            ])
            ->itemLabel(function (array $state) use ($catalog) {
                $label = filled($state['label'] ?? null) ? $state['label'] : ($catalog[$state['item'] ?? '']['label'] ?? ($state['item'] ?? ''));

                return $label . (($state['hidden'] ?? false) ? '  ·  oculto' : '');
            })
            ->addable(false)
            ->deletable(false)
            ->reorderableWithDragAndDrop()
            ->collapsible()
            ->collapsed()
            ->defaultItems(0);
    }

    /**
     * Tira o item da lista em que está e põe no fim do destino (topo ou um grupo).
     */
    protected static function mover(?string $destino, Get $get, Set $set, string $raiz): void
    {
        if (blank($destino)) {
            return;
        }

        $chave = $get('item');
        $topo = $get("{$raiz}topo") ?? [];
        $grupos = $get("{$raiz}groups") ?? [];

        $tirar = function (array $lista) use ($chave, &$item): array {
            foreach ($lista as $id => $atual) {
                if (($atual['item'] ?? null) === $chave) {
                    $item = [...$atual, 'mover' => null];
                    unset($lista[$id]);
                }
            }

            return $lista;
        };

        $item = null;
        $topo = $tirar($topo);
        foreach ($grupos as $id => $grupo) {
            $grupos[$id]['items'] = $tirar($grupo['items'] ?? []);
        }

        if (! $item) {
            return;
        }

        if ($destino === static::TOPO) {
            $topo[(string) Str::uuid()] = $item;
        } else {
            foreach ($grupos as $id => $grupo) {
                if (($grupo['name'] ?? null) === $destino) {
                    $grupos[$id]['items'][(string) Str::uuid()] = $item;
                }
            }
        }

        $set("{$raiz}topo", $topo);
        $set("{$raiz}groups", $grupos);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Salvar menu')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $panelActions = collect(NavigationCatalog::PANELS)
            ->map(fn (string $label, string $id) => Action::make("painel_{$id}")
                ->label($label)
                ->color($this->painel === $id ? 'primary' : 'gray')
                ->url(static::getUrl(['painel' => $id])))
            ->values()
            ->all();

        return [
            ...$panelActions,
            Action::make('reset')
                ->label('Restaurar padrão')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Apaga as configurações deste painel e volta o menu para o que está definido no código.')
                ->action(function () {
                    NavigationItem::where('panel', $this->painel)->delete();
                    NavigationGroup::where('panel', $this->painel)->delete();

                    Notification::make()->title('Menu padrão restaurado')->success()->send();

                    $this->redirect(static::getUrl(['painel' => $this->painel]));
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $catalog = NavigationCatalog::items($this->painel);

        // ordem do menu = ordem da tela: topo primeiro, depois cada grupo
        $linhas = collect($data['topo'] ?? [])->values()->map(fn (array $item) => [...$item, 'group' => null]);
        $groups = collect();

        foreach (array_values($data['groups'] ?? []) as $grupo) {
            $nome = trim((string) ($grupo['name'] ?? ''));

            if ($nome === '' || $groups->contains($nome)) {
                continue;
            }

            $groups->push($nome);
            $linhas = $linhas->merge(collect($grupo['items'] ?? [])->values()->map(fn (array $item) => [...$item, 'group' => $nome]));
        }

        // itens de grupos apagados voltam para o topo
        $perdidos = collect(array_keys($catalog))->diff($linhas->pluck('item'));
        $linhas = $linhas->merge($perdidos->map(fn (string $chave) => ['item' => $chave, 'group' => null, 'label' => null, 'icon' => null, 'hidden' => false]));

        DB::connection('central')->transaction(function () use ($linhas, $catalog, $groups) {
            NavigationGroup::where('panel', $this->painel)->delete();

            foreach ($groups as $index => $name) {
                NavigationGroup::create(['panel' => $this->painel, 'name' => $name, 'sort' => $index]);
            }

            foreach ($linhas->values() as $index => $item) {
                $default = $catalog[$item['item']] ?? null;

                if (! $default) {
                    continue;
                }

                $label = trim((string) ($item['label'] ?? ''));

                NavigationItem::updateOrCreate(
                    ['panel' => $this->painel, 'item' => $item['item']],
                    [
                        'group' => $item['group'],
                        'label' => $label !== '' && $label !== $default['label'] ? $label : null,
                        'icon' => filled($item['icon'] ?? null) && $item['icon'] !== $default['icon'] ? $item['icon'] : null,
                        'sort' => $index,
                        'hidden' => $item['item'] !== static::class && (bool) ($item['hidden'] ?? false),
                    ],
                );
            }
        });

        Notification::make()
            ->title('Menu salvo')
            ->body($perdidos->isNotEmpty() ? $perdidos->count() . ' item(ns) de grupos removidos foram para o topo.' : null)
            ->success()
            ->send();

        // Recarrega para o menu lateral refletir a mudança
        $this->redirect(static::getUrl(['painel' => $this->painel]));
    }

    protected static function iconPlaceholder(?string $icon): string
    {
        return $icon ? 'Padrão: ' . str_replace('heroicon-o-', '', $icon) : 'Sem ícone';
    }
}
