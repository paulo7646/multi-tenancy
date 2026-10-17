<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormDefinitionResource\Pages\CreateFormDefinition;
use App\Filament\Resources\FormDefinitionResource\Pages\EditFormDefinition;
use App\Filament\Resources\FormDefinitionResource\Pages\ListFormDefinitions;
use App\Forms\Enums\ButtonAction;
use App\Forms\Enums\ButtonColor;
use App\Forms\Enums\ButtonPosition;
use App\Forms\Enums\FieldType;
use App\Forms\Enums\FilterType;
use App\Forms\Enums\FormConnection;
use App\Forms\FormGenerator;
use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\Tenant;
use App\Navigation\NavigationCatalog;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use App\Forms\FormPermissions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Construtor de formulários: cada formulário gera tabela (migration),
 * model, controller e tela do Filament, no banco central ou das empresas.
 */
class FormDefinitionResource extends Resource
{
    protected static ?string $model = FormDefinition::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Formulários';

    protected static ?string $modelLabel = 'Formulário';

    protected static ?string $pluralModelLabel = 'Formulários';

    protected static ?string $slug = 'formularios';

    /** Tabelas do próprio sistema que um formulário não pode usar */
    protected const RESERVED_TABLES = [
        'audits', 'cache', 'cache_locks', 'domains', 'failed_jobs', 'filiais', 'form_buttons',
        'form_definitions', 'form_fields', 'form_filters', 'job_batches', 'jobs', 'licencas',
        'migrations', 'model_has_permissions', 'model_has_roles', 'navigation_groups',
        'navigation_items', 'notifications', 'password_reset_tokens', 'permissions',
        'role_has_permissions', 'roles', 'sessions', 'tenants', 'user_licenses', 'users',
    ];

    protected const RESERVED_COLUMNS = ['id', 'created_at', 'updated_at'];

    /**
     * Gera código (que vai para o git) e roda migrations: só em ambiente
     * local, para super_admin. Em produção a tela nem aparece.
     */
    public static function canAccess(): bool
    {
        return app()->environment('local') && (bool) auth()->user()?->hasRole('super_admin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                static::headerSection(),

                Tabs::make('Estrutura')
                    ->tabs([
                        static::fieldsTab(),
                        static::buttonsTab(),
                        static::filtersTab(),
                        static::lookupTab(),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    // ------------------------------------------------------------------
    // Cabeçalho
    // ------------------------------------------------------------------

    protected static function headerSection(): Section
    {
        $locked = fn (?FormDefinition $record) => (bool) $record?->isGenerated();

        return Section::make('Formulário')
            ->description('Depois de gerado, banco, tabela e classes ficam travados.')
            ->icon('heroicon-o-rectangle-stack')
            ->columns(3)
            ->schema([
                TextInput::make('name')
                    ->label('Nome (singular)')
                    ->placeholder('Cliente')
                    ->required()
                    ->maxLength(60)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?FormDefinition $record) {
                        if ($record?->isGenerated() || blank($state)) {
                            return;
                        }

                        $class = Str::studly(Str::ascii($state));

                        foreach (['model_class' => '', 'controller_class' => 'Controller', 'resource_class' => 'Resource'] as $field => $suffix) {
                            if (blank($get($field))) {
                                $set($field, $class . $suffix);
                            }
                        }
                    }),

                TextInput::make('title')
                    ->label('Título (menu, plural)')
                    ->placeholder('Clientes')
                    ->required()
                    ->maxLength(60)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?FormDefinition $record) {
                        if (! $record?->isGenerated() && blank($get('table_name')) && filled($state)) {
                            $set('table_name', Str::snake(Str::ascii($state)));
                        }
                    }),

                ToggleButtons::make('database')
                    ->label('Banco de dados')
                    ->options(FormConnection::class)
                    ->default(FormConnection::Central)
                    ->required()
                    ->inline()
                    ->live()
                    ->disabled($locked),

                TextInput::make('table_name')
                    ->label('Tabela')
                    ->placeholder('clientes')
                    ->required()
                    ->maxLength(40)
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->notIn(static::RESERVED_TABLES)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('database', static::connectionValue($get('database'))))
                    ->rules([fn (Get $get, ?FormDefinition $record) => static::tableMustNotExist($get, $record)])
                    ->validationMessages(['regex' => 'Use letras minúsculas, números e _ (começando por letra).'])
                    ->disabled($locked),

                TextInput::make('model_class')
                    ->label('Classe do model')
                    ->prefix('App\\Models\\Forms\\')
                    ->required()
                    ->regex('/^[A-Z][A-Za-z0-9]*$/')
                    ->unique(ignoreRecord: true)
                    ->disabled($locked),

                TextInput::make('controller_class')
                    ->label('Classe do controller')
                    ->prefix('Controllers\\Forms\\')
                    ->required()
                    ->regex('/^[A-Z][A-Za-z0-9]*$/')
                    ->unique(ignoreRecord: true)
                    ->disabled($locked),

                TextInput::make('resource_class')
                    ->label('Classe da tela (resource)')
                    ->required()
                    ->regex('/^[A-Z][A-Za-z0-9]*Resource$/')
                    ->validationMessages(['regex' => 'Deve começar com maiúscula e terminar em "Resource".'])
                    ->unique(ignoreRecord: true)
                    ->disabled($locked),

                TextInput::make('navigation_group')
                    ->label('Grupo no menu')
                    ->placeholder('Sem grupo (topo)')
                    ->default('Cadastros')
                    ->maxLength(60),

                Select::make('navigation_icon')
                    ->label('Ícone no menu')
                    ->placeholder('document-text')
                    ->searchable()
                    ->allowHtml()
                    ->getSearchResultsUsing(fn (string $search) => NavigationCatalog::searchIcons($search))
                    ->getOptionLabelUsing(fn (?string $value) => $value ? NavigationCatalog::iconLabel($value) : null),

                TagsInput::make('roles')
                    ->label('Papéis com acesso')
                    ->placeholder('Digite o papel e tecle Enter')
                    ->suggestions(fn () => FormPermissions::roleSuggestions())
                    ->helperText('Recebem as permissões da tela (ver, criar, editar, excluir). O super_admin recebe sempre.')
                    ->columnSpan(2),
            ]);
    }

    // ------------------------------------------------------------------
    // Aba Campos
    // ------------------------------------------------------------------

    protected static function fieldsTab(): Tab
    {
        return Tab::make('Campos')
            ->icon('heroicon-o-table-cells')
            ->badge(fn (Get $get) => count($get('fields') ?? []) ?: null)
            ->schema([
                Repeater::make('fields')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->cloneable()
                    ->addActionLabel('Adicionar campo')
                    ->itemLabel(fn (array $state) => static::fieldItemLabel($state))
                    ->deleteAction(fn (Action $action) => $action
                        ->requiresConfirmation()
                        ->modalDescription('Se o formulário já foi gerado, ao salvar a coluna e os dados dela serão apagados da tabela.'))
                    ->schema([
                        // identificador fixo do campo (liga o campo à coluna mesmo se renomear)
                        Hidden::make('uid')->default(fn () => (string) Str::uuid()),

                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                            TextInput::make('label')
                                ->label('Rótulo')
                                ->required()
                                ->maxLength(80)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                    if (blank($get('name')) && filled($state)) {
                                        $name = Str::snake(Str::ascii($state));
                                        $set('name', $get('type') === FieldType::ForeignId->value ? Str::finish($name, '_id') : $name);
                                    }
                                }),

                            TextInput::make('name')
                                ->label('Coluna')
                                ->required()
                                ->maxLength(50)
                                ->distinct()
                                ->regex('/^[a-z][a-z0-9_]*$/')
                                ->notIn(static::RESERVED_COLUMNS)
                                ->rules([fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                    if ($get('type') === FieldType::ForeignId->value && ! str_ends_with((string) $value, '_id')) {
                                        $fail('Chave estrangeira deve terminar em _id (ex.: cliente_id).');
                                    }
                                }])
                                ->validationMessages(['regex' => 'Use letras minúsculas, números e _.']),

                            Select::make('type')
                                ->label('Tipo')
                                ->options(FieldType::groupedOptions())
                                ->default(FieldType::String->value)
                                ->required()
                                ->searchable()
                                ->native(false)
                                ->live(),

                            Grid::make(2)
                                ->schema([
                                    TextInput::make('length')
                                        ->label('Tamanho')
                                        ->numeric()->integer()->minValue(1)->maxValue(10485760)
                                        ->placeholder('255')
                                        ->visible(fn (Get $get) => static::fieldType($get)?->hasLength()),
                                    TextInput::make('precision')
                                        ->label('Precisão')
                                        ->numeric()->integer()->minValue(1)->maxValue(65)
                                        ->placeholder(fn (Get $get) => static::fieldType($get) === FieldType::Decimal ? '10' : '53')
                                        ->visible(fn (Get $get) => static::fieldType($get)?->hasPrecision()),
                                    TextInput::make('scale')
                                        ->label('Casas decimais')
                                        ->numeric()->integer()->minValue(0)->maxValue(30)
                                        ->placeholder('2')
                                        ->visible(fn (Get $get) => static::fieldType($get)?->hasScale()),
                                ]),
                        ]),

                        TagsInput::make('options')
                            ->label('Opções')
                            ->placeholder('Digite uma opção e tecle Enter')
                            ->required(fn (Get $get) => static::fieldType($get)?->hasChoices())
                            ->visible(fn (Get $get) => static::fieldType($get)?->hasChoices()),

                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn (Get $get) => static::fieldType($get)?->isForeign())
                            ->schema([
                                Select::make('lookup_form_id')
                                    ->label('Formulário de referência')
                                    ->helperText('Só aparecem formulários já gerados, do mesmo banco.')
                                    // dentro do repeater $record é o campo; o formulário vem da página
                                    ->options(fn (Get $get, $livewire) => FormDefinition::query()
                                        ->whereNotNull('generated_at')
                                        ->where('database', static::connectionValue($get('../../database')))
                                        ->when($livewire->record ?? null, fn ($query, $form) => $query->whereKeyNot($form->getKey()))
                                        ->pluck('title', 'id'))
                                    ->required(fn (Get $get) => static::fieldType($get)?->isForeign())
                                    ->live(),
                                Select::make('lookup_display')
                                    ->label('Campo exibido')
                                    ->placeholder(fn (Get $get) => 'Padrão: ' . (FormDefinition::find($get('lookup_form_id'))?->lookupColumn() ?? 'id'))
                                    ->options(fn (Get $get) => FormField::where('form_definition_id', $get('lookup_form_id'))->pluck('label', 'name')),
                                Select::make('on_delete')
                                    ->label('Ao excluir o registro de referência')
                                    ->options([
                                        'restrict' => 'Impedir a exclusão',
                                        'cascade' => 'Excluir junto',
                                        'null' => 'Deixar vazio (precisa aceitar vazio)',
                                    ])
                                    ->default('restrict')
                                    ->selectablePlaceholder(false),
                            ]),

                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('default')
                                ->label('Valor padrão')
                                ->maxLength(255)
                                ->hidden(fn (Get $get) => in_array(true, [
                                    static::fieldType($get)?->isForeign(),
                                    static::fieldType($get)?->isJson(),
                                    static::fieldType($get)?->isUpload(),
                                    static::fieldType($get) === FieldType::Password,
                                    static::fieldType($get) === FieldType::MultiSelect,
                                ], true)),
                            TextInput::make('help')
                                ->label('Texto de ajuda')
                                ->maxLength(255),
                        ]),

                        Grid::make(['default' => 2, 'md' => 4, 'xl' => 7])->schema([
                            Toggle::make('nullable')->label('Aceita vazio')->default(true),
                            Toggle::make('required')->label('Obrigatório na tela'),
                            Toggle::make('unique')->label('Único'),
                            Toggle::make('index')->label('Índice'),
                            Toggle::make('show_in_table')->label('Na listagem')->default(true),
                            Toggle::make('searchable')->label('Pesquisável'),
                            Toggle::make('sortable')->label('Ordenável'),
                        ]),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Aba Botões
    // ------------------------------------------------------------------

    protected static function buttonsTab(): Tab
    {
        return Tab::make('Botões')
            ->icon('heroicon-o-cursor-arrow-rays')
            ->badge(fn (Get $get) => count($get('buttons') ?? []) ?: null)
            ->schema([
                Text::make('Botões do tipo "método do controller" ganham um método no controller do formulário, onde você escreve o que ele faz.'),

                Repeater::make('buttons')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderableWithDragAndDrop()
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar botão')
                    ->itemLabel(fn (array $state) => $state['label'] ?? null)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                            TextInput::make('label')
                                ->label('Nome')
                                ->required()
                                ->maxLength(60)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                    if (blank($get('method')) && filled($state)) {
                                        $set('method', Str::camel(Str::ascii($state)));
                                    }
                                }),
                            TextInput::make('method')
                                ->label('Método')
                                ->required()
                                ->distinct()
                                ->regex('/^[a-z][A-Za-z0-9]*$/')
                                ->notIn(['__construct', 'middleware', 'callAction', 'getMiddleware'])
                                ->validationMessages(['regex' => 'camelCase, começando com minúscula (ex.: aprovarPedido).']),
                            Select::make('icon')
                                ->label('Ícone')
                                ->searchable()
                                ->allowHtml()
                                ->getSearchResultsUsing(fn (string $search) => NavigationCatalog::searchIcons($search))
                                ->getOptionLabelUsing(fn (?string $value) => $value ? NavigationCatalog::iconLabel($value) : null),
                            Select::make('color')
                                ->label('Cor')
                                ->options(ButtonColor::class)
                                ->default(ButtonColor::Primary)
                                ->required(),
                            Select::make('position')
                                ->label('Onde aparece')
                                ->options(ButtonPosition::class)
                                ->default(ButtonPosition::Record)
                                ->required(),
                            ToggleButtons::make('action')
                                ->label('Ao clicar')
                                ->options(ButtonAction::class)
                                ->default(ButtonAction::Controller)
                                ->required()
                                ->inline()
                                ->live()
                                ->columnSpan(['md' => 2]),
                            Toggle::make('requires_confirmation')
                                ->label('Pedir confirmação')
                                ->inline(false),
                        ]),
                        TextInput::make('url')
                            ->label('Endereço')
                            ->placeholder('https://... ou /admin/...')
                            ->helperText('Use {id} para o id do registro (botões de linha).')
                            ->required(fn (Get $get) => static::enumValue($get('action')) === ButtonAction::Url->value)
                            ->visible(fn (Get $get) => static::enumValue($get('action')) === ButtonAction::Url->value),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Aba Filtros
    // ------------------------------------------------------------------

    protected static function filtersTab(): Tab
    {
        return Tab::make('Filtros')
            ->icon('heroicon-o-funnel')
            ->badge(fn (Get $get) => count($get('filters') ?? []) ?: null)
            ->schema([
                Repeater::make('filters')
                    ->hiddenLabel()
                    ->relationship()
                    ->orderColumn('sort')
                    ->reorderableWithDragAndDrop()
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar filtro')
                    ->columns(3)
                    ->schema([
                        TextInput::make('label')
                            ->label('Nome')
                            ->required()
                            ->maxLength(60),
                        Select::make('field')
                            ->label('Campo')
                            ->required()
                            ->options(fn (Get $get) => static::fieldOptions($get('../../fields'))),
                        Select::make('type')
                            ->label('Tipo de filtro')
                            ->options(FilterType::class)
                            ->required(),
                    ]),
            ]);
    }

    // ------------------------------------------------------------------
    // Aba Lookup
    // ------------------------------------------------------------------

    protected static function lookupTab(): Tab
    {
        return Tab::make('Lookup')
            ->icon('heroicon-o-link')
            ->schema([
                Text::make('Quando outro formulário tiver um campo de chave estrangeira apontando para este, é este campo que aparece no combo e na listagem (em vez do id).'),

                Select::make('lookup_field')
                    ->label('Campo exibido nos lookups')
                    ->placeholder('id')
                    ->options(fn (Get $get) => static::fieldOptions($get('fields'))),

                TextEntry::make('referenced_by')
                    ->label('Usado como lookup em')
                    ->state(fn (?FormDefinition $record) => $record?->referencedBy()->with('form')->get()
                        ->map(fn (FormField $field) => "{$field->form->title} ({$field->label})")
                        ->join(', ') ?: 'Nenhum formulário ainda')
                    ->visibleOn('edit'),
            ]);
    }

    // ------------------------------------------------------------------
    // Listagem
    // ------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['fields', 'buttons', 'filters']))
            ->columns([
                TextColumn::make('title')->label('Formulário')->searchable()->sortable()
                    ->description(fn (FormDefinition $record) => $record->modelFqcn()),
                TextColumn::make('database')->label('Banco')->badge(),
                TextColumn::make('table_name')->label('Tabela')->searchable()->fontFamily('mono'),
                TextColumn::make('fields_count')->label('Campos')->alignCenter(),
                TextColumn::make('buttons_count')->label('Botões')->alignCenter(),
                TextColumn::make('filters_count')->label('Filtros')->alignCenter(),
                TextColumn::make('generated_at')->label('Gerado em')->dateTime('d/m/Y H:i')->placeholder('Não gerado')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                static::deleteAction(),
            ]);
    }

    /**
     * Exclui o formulário, a tabela e os arquivos gerados (menos o controller).
     */
    public static function deleteAction(): Action
    {
        return Action::make('excluir')
            ->label('Excluir')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (FormDefinition $record) => "Excluir {$record->title}?")
            ->modalDescription(fn (FormDefinition $record) => $record->isGenerated()
                ? "Gera uma migration que apaga a tabela {$record->table_name} e TODOS os dados dela"
                    . ($record->database === FormConnection::Tenant ? ' em todas as empresas' : '')
                    . ' (aqui na hora; no cliente, ao rodar a migration). Os arquivos de model e tela são apagados'
                    . ' (recuperáveis pelo git); o controller é mantido.'
                : 'O formulário ainda não foi gerado; só o cadastro será apagado.')
            ->schema(fn (FormDefinition $record) => $record->isGenerated() ? [
                TextInput::make('confirmacao')
                    ->label("Digite {$record->table_name} para confirmar")
                    ->required()
                    ->in([$record->table_name]),
            ] : [])
            ->action(function (FormDefinition $record, Action $action) {
                try {
                    FormGenerator::destroy($record);
                    $record->delete();
                } catch (Throwable $e) {
                    Notification::make()->title('Não foi possível excluir')->body($e->getMessage())->danger()->persistent()->send();

                    $action->halt();
                }

                Notification::make()->title('Formulário excluído')->success()->send();
            })
            ->successRedirectUrl(static::getUrl('index'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormDefinitions::route('/'),
            'create' => CreateFormDefinition::route('/create'),
            'edit' => EditFormDefinition::route('/{record}/edit'),
        ];
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    protected static function fieldType(Get $get): ?FieldType
    {
        return FieldType::tryFrom((string) static::enumValue($get('type')));
    }

    protected static function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    protected static function connectionValue(mixed $value): string
    {
        return (string) (static::enumValue($value) ?: FormConnection::Central->value);
    }

    /**
     * @return array<string, string> coluna => rótulo, a partir do estado do repeater de campos
     */
    protected static function fieldOptions(?array $fields): array
    {
        return collect($fields ?? [])
            ->filter(fn (array $field) => filled($field['name'] ?? null))
            ->mapWithKeys(fn (array $field) => [$field['name'] => ($field['label'] ?? null) ?: $field['name']])
            ->all();
    }

    protected static function fieldItemLabel(array $state): ?string
    {
        if (blank($state['label'] ?? null)) {
            return null;
        }

        $type = FieldType::tryFrom((string) static::enumValue($state['type'] ?? ''));

        return $state['label'] . '  ·  ' . ($state['name'] ?? '?') . '  ·  ' . ($type?->value ?? '?');
    }

    /**
     * Na criação, a tabela não pode existir no banco (central, ou na primeira empresa).
     */
    protected static function tableMustNotExist(Get $get, ?FormDefinition $record): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($get, $record) {
            if ($record?->isGenerated() || blank($value)) {
                return;
            }

            $connection = static::connectionValue($get('database'));

            $exists = $connection === FormConnection::Central->value
                ? DbSchema::connection('central')->hasTable($value)
                : (bool) Tenant::first()?->run(fn () => DbSchema::hasTable($value));

            if ($exists) {
                $fail("A tabela {$value} já existe nesse banco.");
            }
        };
    }
}
