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
use Livewire\Attributes\Url;

/**
 * Configura o menu lateral de cada painel: grupos (nome e ordem) e,
 * para cada página, grupo, nome, ícone, ordem e visibilidade.
 */
class NavigationSettings extends Page
{
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
        return 'Arraste para ordenar. Ocultar um item só o tira do menu: quem tem permissão ainda consegue abrir a página pelo endereço.';
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
            ->map(function (array $default, string $class) use ($savedItems) {
                $saved = $savedItems->get($class);

                return [
                    'item' => $class,
                    'group' => $saved ? $saved->group : $default['group'],
                    'label' => $saved?->label,
                    'icon' => $saved?->icon,
                    'hidden' => (bool) $saved?->hidden,
                    // itens novos (sem configuração) vão para o fim
                    '_sort' => $saved ? $saved->sort : 100000 + $default['sort'],
                ];
            })
            ->sortBy('_sort')
            ->map(fn (array $item) => collect($item)->except('_sort')->all())
            ->values();

        $groups = $savedGroups
            ->merge(collect($catalog)->pluck('group')->filter())
            ->unique()
            ->values()
            ->map(fn (string $name) => ['name' => $name, 'original' => $name]);

        $this->form->fill([
            'groups' => $groups->all(),
            'items' => $items->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $catalog = NavigationCatalog::items($this->painel);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Grupos')
                    ->description('Ordem dos grupos no menu. Itens sem grupo ficam sempre no topo.')
                    ->icon('heroicon-o-folder')
                    ->collapsible()
                    ->schema([
                        Repeater::make('groups')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('original'),
                                TextInput::make('name')
                                    ->hiddenLabel()
                                    ->required()
                                    ->distinct()
                                    ->live(onBlur: true)
                                    // Renomear o grupo leva junto os itens que estavam nele
                                    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set) {
                                        if (blank($old) || $state === $old) {
                                            return;
                                        }

                                        $items = collect($get('../../items'))
                                            ->map(fn (array $item) => $item['group'] === $old ? [...$item, 'group' => $state] : $item)
                                            ->all();

                                        $set('../../items', $items);
                                    }),
                            ])
                            ->addActionLabel('Novo grupo')
                            ->reorderableWithDragAndDrop()
                            ->defaultItems(0)
                            ->grid(3),
                    ]),

                Section::make('Itens do menu')
                    ->description('Troque o grupo, o nome e o ícone de cada página. Deixe nome e ícone vazios para usar o padrão.')
                    ->icon('heroicon-o-queue-list')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('item'),
                                Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                                    Select::make('group')
                                        ->label('Grupo')
                                        ->placeholder('Sem grupo (topo)')
                                        ->options(fn (Get $get) => collect($get('../../groups'))
                                            ->pluck('name')
                                            ->filter()
                                            ->mapWithKeys(fn (string $name) => [$name => $name])
                                            ->all()),
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
                                    Toggle::make('hidden')
                                        ->label('Ocultar do menu')
                                        ->inline(false)
                                        // esta tela não pode sumir do menu
                                        ->disabled(fn (Get $get) => $get('item') === static::class),
                                ]),
                            ])
                            ->itemLabel(function (array $state) use ($catalog) {
                                $label = filled($state['label'] ?? null) ? $state['label'] : ($catalog[$state['item']]['label'] ?? $state['item']);
                                $group = filled($state['group'] ?? null) ? $state['group'] : 'Sem grupo';

                                return $label . '  ·  ' . $group . (($state['hidden'] ?? false) ? '  ·  oculto' : '');
                            })
                            ->addable(false)
                            ->deletable(false)
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->collapsed(),
                    ]),
            ]);
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

        $groups = collect($data['groups'] ?? [])
            ->pluck('name')
            ->map(fn (?string $name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        DB::connection('central')->transaction(function () use ($data, $catalog, $groups) {
            NavigationGroup::where('panel', $this->painel)->delete();

            foreach ($groups as $index => $name) {
                NavigationGroup::create(['panel' => $this->painel, 'name' => $name, 'sort' => $index]);
            }

            foreach (array_values($data['items'] ?? []) as $index => $item) {
                $default = $catalog[$item['item']] ?? null;

                if (! $default) {
                    continue;
                }

                $label = trim((string) ($item['label'] ?? ''));

                NavigationItem::updateOrCreate(
                    ['panel' => $this->painel, 'item' => $item['item']],
                    [
                        // grupo apagado da lista: o item vai para o topo
                        'group' => $groups->contains($item['group'] ?? null) ? $item['group'] : null,
                        'label' => $label !== '' && $label !== $default['label'] ? $label : null,
                        'icon' => filled($item['icon'] ?? null) && $item['icon'] !== $default['icon'] ? $item['icon'] : null,
                        'sort' => $index,
                        'hidden' => $item['item'] !== static::class && (bool) ($item['hidden'] ?? false),
                    ],
                );
            }
        });

        Notification::make()->title('Menu salvo')->success()->send();

        // Recarrega para o menu lateral refletir a mudança
        $this->redirect(static::getUrl(['painel' => $this->painel]));
    }

    protected static function iconPlaceholder(?string $icon): string
    {
        return $icon ? 'Padrão: ' . str_replace('heroicon-o-', '', $icon) : 'Sem ícone';
    }
}
