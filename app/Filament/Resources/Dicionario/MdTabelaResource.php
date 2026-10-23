<?php

namespace App\Filament\Resources\Dicionario;

use App\Filament\Resources\Dicionario\Concerns\AcessoDicionario;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages;
use App\Models\Md\MdStatus;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Navigation\NavigationCatalog;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Enums\TipoTabela;
use App\Models\Md\MdTabelaItem;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use App\Sistema\Runtime\DiagramaSchema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Collection;
use App\Sistema\Geradores\Gerador;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use App\Sistema\Runtime\Traducao;
use Livewire\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tabelas do dicionário: cada uma gera a migration e o model
 * (app/Modulos/{módulo}/{nome físico}/ModelProduto.php).
 */
class MdTabelaResource extends Resource
{
    use AcessoDicionario;

    protected static ?string $model = MdTabela::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $modelLabel = 'Tabela';

    protected static ?string $pluralModelLabel = 'Tabelas';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'dicionario/tabelas';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('tabela')
                    ->tabs([
                        static::abaTabela(),
                        static::abaCampos(),
                        static::abaSituacoes(),
                        static::abaItens(),
                        static::abaTraducao(Traducao::TABELA),
                        static::abaTraducao(Traducao::LOOKUP),
                        static::abaTelas('formularios'),
                        static::abaTelas('gerenciamentos'),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    // ------------------------------------------------------------------

    protected static function abaTabela(): Tab
    {
        $gerada = fn(?MdTabela $record) => (bool) $record?->isGerada();

        return Tab::make('Tabela')
            ->icon('heroicon-o-table-cells')
            ->schema([
                Tabs::make('tabela')
                    ->tabs([
                        Tab::make('Geral')
                            ->columns(3)
                            ->icon('heroicon-o-table-cells')
                            ->schema([
                                Select::make('modulo_id')
                                    ->label('Módulo')
                                    ->relationship('modulo', 'nome')
                                    ->required()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?MdTabela $record) {
                                        if (! $record?->isGerada()) {
                                            $set('banco', Modulo::find($get('modulo_id'))?->banco?->value);
                                        }

                                        static::sugerirNomeFisico($get, $set, $record);
                                    })
                                    ->disabled($gerada),

                                TextInput::make('nome')
                                    ->required()
                                    ->maxLength(60)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set, ?MdTabela $record) => static::sugerirNomeFisico($get, $set, $record)),
                                Campos::nomeFisico()
                                    ->placeholder('OP_PRODUTO')
                                    ->notIn([...Campos::TABELAS_RESERVADAS, ...array_map('mb_strtoupper', Campos::TABELAS_RESERVADAS)])
                                    ->unique(ignoreRecord: true)
                                    ->rules([
                                        fn(Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $prefixo = Modulo::find($get('modulo_id'))?->prefixo;

                                            if ($prefixo && ! str_starts_with(mb_strtoupper((string) $value), MdTabela::prefixoFisico($prefixo))) {
                                                $fail('Deve começar com o prefixo do módulo: ' . MdTabela::prefixoFisico($prefixo));
                                            }
                                        },
                                        fn(Get $get, ?MdTabela $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                            if ($record?->isGerada()) {
                                                return;
                                            }

                                            $banco = Banco::tryFrom((string) static::valorBanco($get('banco')));

                                            if ($banco === Banco::Central && DbSchema::connection('central')->hasTable($value)) {
                                                $fail("Já existe uma tabela {$value} no banco central.");
                                            }
                                        },
                                    ])
                                    ->helperText(fn(?MdTabela $record) => $record?->isGerada()
                                        ? "Model: {$record->classeModel()}"
                                        : 'Fica fixo depois de salvar (é a tabela no banco e a pasta dos arquivos).')
                                    ->disabled($gerada),

                                Campos::nomeArquivo('Nome do controller', 'nome_controller')
                                    ->placeholder('ControllerProduto')
                                    ->helperText('Classe de regras de negócio da tabela. Fica fixo depois de salvar.')
                                    ->disabled($gerada),
                                Campos::icone()->helperText('Ícone no menu (gerenciamentos desta tabela).'),
                                Campos::cor(padrao: '')->label('Cor do ícone')->required(false)->placeholder('Padrão do tema'),

                                Textarea::make('descricao')
                                    ->label('Descrição')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Parâmetros')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        ToggleButtons::make('tipo')
                                            ->label('Tipo da tabela')
                                            ->options(TipoTabela::class)
                                            ->default(TipoTabela::Cadastro)
                                            ->required()
                                            ->inline()
                                            ->live()
                                            ->helperText(fn(Get $get) => TipoTabela::tryFrom(static::valorTipo($get('tipo')))?->getDescription())
                                            // gerada: só dá para trocar entre cadastro e ligação (a estrutura no banco é a mesma)
                                            ->disableOptionWhen(fn(string $value, ?MdTabela $record) => (bool) $record?->isGerada()
                                                && ! ($record->tipoTabela()->editavel() && TipoTabela::from($value)->editavel())),

                                        ToggleButtons::make('banco')
                                            ->label('Gerar a tabela em')
                                            ->options(Banco::class)
                                            ->required()
                                            ->inline()
                                            ->helperText('Vem do módulo.')
                                            ->disabled()
                                            ->dehydrated(),

                                        Toggle::make('criar_telas')
                                            ->label('Criar formulário e gerenciamento padrão')
                                            ->helperText('Já cria o formulário e a tela (ProdutoResource, com permissões no Shield) com todos os campos.')
                                            ->default(true)
                                            ->dehydrated(false)
                                            // tabela de ligação não tem telas
                                            ->visible(fn (Get $get, string $operation) => $operation === 'create'
                                                && (TipoTabela::tryFrom(static::valorTipo($get('tipo')))?->temTelas() ?? true)),

                                        Toggle::make('controla_situacao')
                                            ->label('Controla situação')
                                            ->helperText('Cria a coluna SITUACAO na tabela, com as situações escolhidas na aba Situação (ícone e cor no datagrid e no formulário).')
                                            ->live(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected static function abaCampos(): Tab
    {
        return Tab::make('Campos')
            ->icon('heroicon-o-list-bullet')
            ->badge(fn(Get $get) => count($get('campos') ?? []) ?: null)
            ->schema([
                Hidden::make('nome_fisico_sugerido')->dehydrated(false),
                Hidden::make('nome_controller_sugerido')->dehydrated(false),

                Repeater::make('campos')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->relationship('campos')
                    ->orderColumn('ordem')
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->addActionLabel('Adicionar campo')
                    ->itemLabel(fn(array $state) => filled($state['nome'] ?? null)
                        ? "{$state['nome']} · " . ($state['nome_fisico'] ?? '') . ' · ' . (TipoCampo::tryFrom($state['tipo'] ?? '')?->label() ?? '')
                        : 'Novo campo')
                    // campos do sistema (lista/conteúdo) não podem ser apagados
                    ->deleteAction(fn(Action $action) => $action->hidden(fn(array $arguments, Repeater $component) => (bool) ($component->getRawItemState($arguments['item'])['sistema'] ?? false)))
                    ->schema([
                        Hidden::make('uid')->default(fn() => (string) Str::uuid()),
                        Hidden::make('sistema')->default(false),

                        Tabs::make('Geral')
                            ->tabs([
                                Tab::make('Geral')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome (título no formulário)')
                                            ->required()
                                            ->maxLength(60)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                                if (blank($get('nome_fisico'))) {
                                                    $set('nome_fisico', Campos::snake($state));
                                                }
                                            }),

                                        Campos::nomeFisico()
                                            ->notIn(Campos::COLUNAS_RESERVADAS)
                                            ->distinct()
                                            ->disabled(fn(Get $get) => (bool) $get('sistema'))
                                            ->validationMessages(['not_in' => 'ID, CREATED_AT, UPDATED_AT e SITUACAO são criados pelo sistema.']),

                                        Select::make('tipo')
                                            ->options(TipoCampo::groupedOptions())
                                            ->default(TipoCampo::String->value)
                                            ->required()
                                            ->searchable()
                                            ->disabled(fn(Get $get) => (bool) $get('sistema'))
                                            ->live(),

                                        TextInput::make('tamanho')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(10485760)
                                            ->placeholder(fn(Get $get) => TipoCampo::tryFrom((string) $get('tipo'))?->defaultLength())
                                            ->visible(fn(Get $get) => TipoCampo::tryFrom((string) $get('tipo'))?->storageType()->hasLength()),

                                        TextInput::make('precisao')
                                            ->label('Precisão (dígitos)')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(65)
                                            ->placeholder('10')
                                            ->visible(fn(Get $get) => TipoCampo::tryFrom((string) $get('tipo'))?->hasPrecision()),

                                        TextInput::make('escala')
                                            ->label('Casas decimais')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->maxValue(30)
                                            ->placeholder('2')
                                            ->visible(fn(Get $get) => TipoCampo::tryFrom((string) $get('tipo'))?->hasScale()),

                                        TagsInput::make('opcoes')
                                            ->label('Opções')
                                            ->placeholder('Digite e tecle Enter')
                                            ->required(fn(Get $get) => TipoCampo::tryFrom((string) $get('tipo'))?->hasChoices())
                                            ->visible(fn(Get $get) => TipoCampo::tryFrom((string) $get('tipo'))?->hasChoices())
                                            ->columnSpan(2),

                                        Select::make('fk_tabela_id')
                                            ->label('Tabela da chave estrangeira')
                                            ->options(fn(Get $get) => MdTabela::query()
                                                ->where('banco', static::valorBanco($get('../../banco')))
                                                ->whereNotNull('generated_at')
                                                ->orderBy('nome_fisico')
                                                ->get()
                                                ->mapWithKeys(fn(MdTabela $t) => [$t->id => "{$t->nome} ({$t->nome_fisico})"])
                                                ->all())
                                            ->helperText('Só tabelas já geradas, do mesmo banco.')
                                            ->required(fn(Get $get) => $get('tipo') === TipoCampo::ForeignId->value)
                                            ->visible(fn(Get $get) => $get('tipo') === TipoCampo::ForeignId->value)
                                            ->searchable(),

                                        Select::make('fk_on_delete')
                                            ->label('Ao excluir o registro apontado')
                                            ->options(['restrict' => 'Impedir a exclusão', 'cascade' => 'Excluir junto', 'null' => 'Deixar vazio'])
                                            // tabela de ligação: apagou um dos lados, a linha de ligação vai junto
                                            ->default(fn(Get $get) => TipoTabela::tryFrom(static::valorTipo($get('../../tipo'))) === TipoTabela::Ligacao ? 'cascade' : 'restrict')
                                            ->helperText(fn(Get $get) => TipoTabela::tryFrom(static::valorTipo($get('../../tipo'))) === TipoTabela::Ligacao
                                                ? 'Em tabela de ligação, use "Excluir junto": senão o registro apontado não pode ser excluído enquanto estiver ligado.'
                                                : null)
                                            ->required()
                                            ->visible(fn(Get $get) => $get('tipo') === TipoCampo::ForeignId->value),

                                        TextInput::make('padrao')
                                            ->label('Valor padrão')
                                            ->maxLength(255)
                                            ->visible(fn(Get $get) => ! in_array($get('tipo'), [TipoCampo::ForeignId->value, TipoCampo::Password->value, TipoCampo::File->value, TipoCampo::Image->value], true)),

                                        TextInput::make('ajuda')
                                            ->label('Texto de ajuda')
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                    ]),

                                Tab::make('Parâmetros')
                                    ->columns(4)
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        Toggle::make('obrigatorio')->label('Obrigatório')->inline(false)->disabled(fn(Get $get) => (bool) $get('sistema')),
                                        Toggle::make('unico')->label('Único')->inline(false)->disabled(fn(Get $get) => (bool) $get('sistema')),
                                        Toggle::make('indice')->label('Índice')->inline(false),
                                        Toggle::make('sem_componente')->label('Não cria componente')->inline(false),
                                    ]),

                            ]),
                    ]),
            ]);
    }

    protected static function abaSituacoes(): Tab
    {
        return Tab::make('Situação')
            ->icon('heroicon-o-flag')
            ->visible(fn(Get $get) => (bool) $get('controla_situacao'))
            ->schema([
                Repeater::make('situacoes')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->relationship('situacoes')
                    ->orderColumn('ordem')
                    ->reorderable()
                    ->addActionLabel('Adicionar situação')
                    ->minItems(fn(Get $get) => $get('controla_situacao') ? 1 : 0)
                    ->columns(2)
                    ->rules([
                        fn() => function (string $attribute, $value, Closure $fail) {
                            if (collect($value)->where('padrao', true)->count() > 1) {
                                $fail('Marque só uma situação como padrão.');
                            }
                        },
                    ])
                    ->schema([
                        Select::make('md_status_id')
                            ->label('Situação')
                            ->options(fn() => MdStatus::orderBy('nome')->get()->mapWithKeys(fn(MdStatus $s) => [
                                $s->id => ($s->icone ? NavigationCatalog::iconLabel($s->icone) : '') . ' ' . e($s->nome),
                            ])->all())
                            ->allowHtml()
                            ->required()
                            ->distinct()
                            ->helperText('Cadastre outras em Dicionário de dados > Situações.'),

                        Toggle::make('padrao')
                            ->label('Padrão ao incluir')
                            ->inline(false),
                    ]),
            ]);
    }

    protected static function abaTraducao(string $tipo): Tab
    {

        return Tab::make($tipo === Traducao::LOOKUP ? 'Tradução de lookup' : 'Tradução de tabela')
            ->icon($tipo === Traducao::LOOKUP ? 'heroicon-o-magnifying-glass' : 'heroicon-o-language')
            ->schema([
                Text::make($tipo === Traducao::LOOKUP
                    ? 'Como um registro desta tabela aparece nos campos de formulário de outras tabelas que apontam para ela (o combo da chave estrangeira). A pesquisa procura em todos os campos da tradução. Cada componente de formulário escolhe a sua (sem escolher, vale a padrão).'
                    : 'Como um registro desta tabela aparece nas colunas dos gerenciamentos (datagrid) de outras tabelas que apontam para ela. Cada coluna escolhe a sua (sem escolher, vale a padrão).'),

                Repeater::make($tipo === Traducao::LOOKUP ? 'traducoesLookup' : 'traducoesTabela')
                    ->hiddenLabel()
                    ->relationship($tipo === Traducao::LOOKUP ? 'traducoesLookup' : 'traducoesTabela')
                    ->mutateRelationshipDataBeforeCreateUsing(fn(array $data) => [...$data, 'tipo' => $tipo])
                    ->orderColumn('ordem')
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar tradução')
                    ->itemLabel(fn(array $state, Component $livewire) => ($state['nome'] ?? 'Nova tradução') . (($state['padrao'] ?? false) ? '  ·  padrão' : '') . '  ·  ' . static::exemploTraducao($state['campos'] ?? [], $livewire))
                    ->rules([
                        fn() => function (string $attribute, $value, Closure $fail) {
                            if (collect($value)->where('padrao', true)->count() > 1) {
                                $fail('Marque só uma tradução como padrão.');
                            }
                        },
                    ])
                    ->schema([

                        Tabs::make('Geral')
                            ->tabs([
                                Tab::make('Geral')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('nome')
                                            ->required()
                                            ->maxLength(60)
                                            ->placeholder('Ex.: Código e nome'),

                                        Repeater::make('campos')
                                            ->label('Campos (em ordem)')
                                            ->simple(
                                                Select::make('campo')
                                                    ->options(fn(Component $livewire) => static::opcoesTraducao($livewire))
                                                    ->required()
                                                    ->distinct(),
                                            )
                                            ->reorderable()
                                            ->defaultItems(1)
                                            ->minItems(1)
                                            ->addActionLabel('Adicionar campo')
                                            ->live()
                                            ->columnSpanFull(),

                                    ]),

                                Tab::make('Parâmetros')
                                    ->columns(4)
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->schema([
                                        Toggle::make('padrao')
                                            ->label('Padrão')
                                            ->inline(false),
                                    ]),

                            ]),
                    ]),
            ]);
    }


    protected static function sugerirNomeFisico(Get $get, Set $set, ?MdTabela $record): void
    {
        if ($record?->isGerada()) {
            return;
        }

        $prefixo = Modulo::find($get('modulo_id'))?->prefixo;
        $nome = Campos::snake($get('nome'));
        $atual = (string) $get('nome_fisico');

        if (! $prefixo || ! $nome) {
            return;
        }

        // só troca se estiver vazio ou ainda for a última sugestão automática
        if ($atual === '' || $atual === $get('nome_fisico_sugerido')) {
            $set('nome_fisico', MdTabela::prefixoFisico($prefixo) . $nome);
            $set('nome_fisico_sugerido', MdTabela::prefixoFisico($prefixo) . $nome);
        }

        $controller = 'Controller' . Str::studly($nome);
        $atualController = (string) $get('nome_controller');

        if ($atualController === '' || $atualController === $get('nome_controller_sugerido')) {
            $set('nome_controller', $controller);
            $set('nome_controller_sugerido', $controller);
        }
    }

    protected static function abaItens(): Tab
    {

        $tipo = fn(Get $get) => TipoTabela::tryFrom(static::valorTipo($get('tipo')));

        return Tab::make('Itens')
            ->icon('heroicon-o-list-bullet')
            ->visible(fn(Get $get) => (bool) $tipo($get)?->temItens())
            ->schema([
                Text::make(fn(Get $get, ?MdTabela $record) => $tipo($get) === TipoTabela::Lista
                    ? 'Cada item vira um caso do enum ' . ($record?->exists ? $record->enumFqcn() : 'do módulo') . ', com código inteiro sequencial (1, 2, 3...) definido pelo sistema, e um registro da tabela (igual em todos os bancos).'
                    : 'Cada item vira um caso do enum ' . ($record?->exists ? $record->enumFqcn() : 'do módulo') . ', com o valor que você definir, e um registro da tabela (igual em todos os bancos).'),

                Repeater::make('itens')
                    ->hiddenLabel()
                    ->relationship('itens')
                    ->orderColumn('ordem')
                    ->reorderable()
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar item')
                    ->columns(3)
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Código')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('automático')
                            ->visible(fn(Get $get) => $tipo($get) === TipoTabela::Lista || TipoTabela::tryFrom(static::valorTipo($get('../../tipo'))) === TipoTabela::Lista),

                        TextInput::make('valor')
                            ->label('Valor')
                            ->maxLength(60)
                            ->distinct()
                            ->required(fn(Get $get) => TipoTabela::tryFrom(static::valorTipo($get('../../tipo'))) === TipoTabela::Conteudo)
                            ->visible(fn(Get $get) => TipoTabela::tryFrom(static::valorTipo($get('../../tipo'))) === TipoTabela::Conteudo),

                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(100)
                            ->distinct()
                            ->helperText(fn(?string $state) => filled($state) ? 'Caso no enum: ' . MdTabelaItem::nomeDoCaso($state) : null)
                            ->live(onBlur: true)
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function valorTipo(mixed $tipo): ?string
    {
        return $tipo instanceof TipoTabela ? $tipo->value : $tipo;
    }

    /**
     * @return array<string, string> uid => nome (nome físico)
     */
    protected static function opcoesTraducao(Component $livewire): array
    {
        return collect($livewire->data['campos'] ?? [])
            ->filter(fn($c) => filled($c['uid'] ?? null) && filled($c['nome_fisico'] ?? null)
                && ($tipo = TipoCampo::tryFrom((string) ($c['tipo'] ?? ''))) && Traducao::aceita($tipo))
            ->mapWithKeys(fn($c) => [$c['uid'] => ($c['nome'] ?? $c['nome_fisico']) . " ({$c['nome_fisico']})"])
            ->all();
    }

    protected static function exemploTraducao(array $uids, Component $livewire): string
    {
        $nomes = collect($livewire->data['campos'] ?? [])->keyBy('uid');
        // no formulário, cada item do repeater simples vem como ['campo' => uid]
        $partes = collect($uids)->map(fn($uid) => $nomes->get(is_array($uid) ? ($uid['campo'] ?? null) : $uid)['nome_fisico'] ?? null)->filter()->values();

        return $partes->isEmpty() ? 'id (sem tradução)' : $partes->join(Traducao::SEPARADOR);
    }

    /**
     * Formulários ou gerenciamentos desta tabela, com atalhos.
     */
    protected static function abaTelas(string $tipo): Tab
    {
        return Tab::make($tipo === 'formularios' ? 'Formulários' : 'Gerenciamentos')
            ->icon($tipo === 'formularios' ? 'heroicon-o-document-text' : 'heroicon-o-queue-list')
            ->visible(fn(?MdTabela $record) => (bool) $record?->exists)
            ->badge(fn(?MdTabela $record) => $record ? ($tipo === 'formularios' ? $record->formularios()->count() : $record->gerenciamentos()->count()) ?: null : null)
            ->schema([
                View::make('filament.sistema.tabela-telas')
                    ->viewData(fn(?MdTabela $record) => [
                        'tipo' => $tipo,
                        'itens' => $record ? ($tipo === 'formularios' ? $record->formularios()->orderBy('id')->get() : $record->gerenciamentos()->with('formulario')->orderBy('ordem')->get()) : collect(),
                        'urlNovo' => $record ? ($tipo === 'formularios' ? MdFormularioResource::getUrl('create', ['tabela' => $record->id]) : MdGerenciamentoResource::getUrl('create', ['tabela' => $record->id])) : null,
                        'urlEditar' => fn($item) => $tipo === 'formularios' ? MdFormularioResource::getUrl('edit', ['record' => $item]) : MdGerenciamentoResource::getUrl('edit', ['record' => $item]),
                    ]),
            ]);
    }

    public static function valorBanco(mixed $banco): ?string
    {
        return $banco instanceof Banco ? $banco->value : $banco;
    }

    // ------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with('modulo')->withCount('campos'))
            ->defaultSort('nome_fisico')
            ->columns([
                TextColumn::make('modulo.nome')->label('Módulo')->badge()->sortable(),
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('nome_fisico')->label('Nome físico')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('banco')->badge(),
                TextColumn::make('campos_count')->label('Campos'),
                IconColumn::make('controla_situacao')->label('Situação')->boolean(),
                TextColumn::make('generated_at')->label('Gerada em')->dateTime('d/m/Y H:i')->placeholder('não gerada'),
            ])
            ->filters([
                SelectFilter::make('modulo_id')->label('Módulo')->relationship('modulo', 'nome'),
                SelectFilter::make('banco')->options(Banco::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::excluir(DeleteAction::make()),
            ])
            // selecionou tabelas no datagrid: o schema delas e das filhas
            ->toolbarActions([
                BulkAction::make('schema')
                    ->label('Schema')
                    ->icon('heroicon-o-share')
                    ->color('gray')
                    ->modalHeading(fn (Collection $records) => 'Schema: ' . $records->pluck('nome_fisico')->join(', ') . ' e tabelas filhas')
                    ->modalWidth(Width::Screen)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (Collection $records) => view('filament.dicionario.schema', [
                        'codigo' => DiagramaSchema::daTabela($records->modelKeys()),
                    ]))
                    ->action(fn () => null),
            ]);
    }

    /**
     * "Schema" do topo da lista: todas as tabelas e ligações (filtro por banco).
     */
    public static function acaoSchemaGeral(): Action
    {
        return Action::make('schema')
            ->label('Schema')
            ->icon('heroicon-o-share')
            ->color('gray')
            ->modalHeading('Schema de todas as tabelas')
            ->modalWidth(Width::Screen)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn () => view('filament.dicionario.schema', ['codigo' => DiagramaSchema::geral()]))
            // por banco: as FKs só ligam tabelas do mesmo banco
            ->extraModalFooterActions(fn () => collect(Banco::cases())->map(fn (Banco $banco) => Action::make("schema_{$banco->value}")
                ->label('Só ' . mb_strtolower($banco->getLabel()))
                ->color('gray')
                ->modalHeading('Schema: ' . $banco->getLabel())
                ->modalWidth(Width::Screen)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(view('filament.dicionario.schema', ['codigo' => DiagramaSchema::geral($banco->value)])))
                ->all());
    }

    public static function excluir(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalDescription('Gera a migration que apaga a tabela (e os dados), remove o model e as permissões das telas. Os arquivos do Resource, do controller e dos botões são mantidos.')
            ->before(function (DeleteAction $action, MdTabela $record) {
                try {
                    Gerador::excluirTabela($record);
                } catch (Throwable $e) {
                    Notification::make()->title('Não foi possível excluir')->body($e->getMessage())->danger()->persistent()->send();
                    $action->cancel();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMdTabelas::route('/'),
            'create' => Pages\CreateMdTabela::route('/create'),
            'view' => Pages\ViewMdTabela::route('/{record}'),
            'edit' => Pages\EditMdTabela::route('/{record}/edit'),
        ];
    }
}
