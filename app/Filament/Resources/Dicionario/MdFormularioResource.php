<?php

namespace App\Filament\Resources\Dicionario;

use App\Filament\Resources\Dicionario\Concerns\AcessoDicionario;
use App\Filament\Resources\Dicionario\MdFormularioResource\Pages;
use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdFormularioPagina;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Enums\TipoComponente;
use App\Sistema\Enums\TipoTabPage;
use App\Sistema\Enums\TipoTabela;
use App\Sistema\Runtime\Ligacao;
use App\Sistema\Runtime\Layout;
use App\Sistema\Runtime\Traducao;
use Closure;
use App\Sistema\Runtime\FormularioBuilder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Support\Enums\Width;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Formulários das tabelas: os componentes (tabela filha md_formulario_componentes),
 * botões. A personalização fica no Resource do gerenciamento (ProdutoResource.php).
 */
class MdFormularioResource extends Resource
{
    use AcessoDicionario;

    protected static ?string $model = MdFormulario::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $modelLabel = 'Formulário';

    protected static ?string $pluralModelLabel = 'Formulários';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'dicionario/formularios';

    /** @var array<int, Collection> */
    protected static array $camposCache = [];

    public static function form(Schema $schema): Schema
    {
        // dentro dos repeaters o Get é relativo ao item (componente de tab page: dois níveis)
        $tabela = fn (Get $get) => MdTabela::with('modulo')->find($get('md_tabela_id') ?? $get('../../md_tabela_id') ?? $get('../../../../md_tabela_id'));

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('formulario')
                    ->tabs([
                        Tab::make('Geral')
                            ->icon('heroicon-o-rectangle-stack')
                            ->columns(3)
                            ->schema([
                                Select::make('md_tabela_id')
                                    ->label('Tabela')
                                    ->relationship('tabela', 'nome', fn ($query) => $query->whereNotNull('generated_at'))
                                    ->getOptionLabelFromRecordUsing(fn (MdTabela $record) => "{$record->nome} ({$record->nome_fisico})")
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => static::sugerir($get, $set))
                                    ->disabledOn('edit'),
        
                                TextInput::make('nome')->required()->maxLength(60),
        
                                TextInput::make('titulo')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(100)
                                    ->helperText('Aparece no topo da tela.'),
        
                                Campos::nomeFisico()
                                    ->unique(ignoreRecord: true)
                                    ->disabledOn('edit'),
        
                                Hidden::make('nome_arquivo'),

                                Select::make('colunas')
                                    ->label('Colunas')
                                    ->options([1 => '1 coluna', 2 => '2 colunas', 3 => '3 colunas', 4 => '4 colunas'])
                                    ->default(2)
                                    ->required(),
                            ]),

                        static::abaComponentes($tabela),

                        static::abaTabPages($tabela),

                        Tab::make('Botões')
                            ->icon('heroicon-o-cursor-arrow-rays')
                            ->schema([
                                Repeater::make('botoes')
                                    ->hiddenLabel()
                                    ->defaultItems(0)
                                    ->relationship('botoes')
                                    ->orderColumn('ordem')
                                    ->reorderable()
                                    ->collapsible()
                                    ->addActionLabel('Adicionar botão')
                                    ->itemLabel(fn (array $state) => $state['titulo'] ?? 'Novo botão')
                                    ->columns(4)
                                    ->schema(static::camposBotao($tabela, fn (Get $get, Component $livewire) => Campos::botoesDaTabela($tabela($get), ignorarFormulario: $livewire->record?->id ?? 0))),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    /**
     * Aba "Componentes": os componentes da tab "Geral" (sem tab page). Cada
     * componente é um campo da tabela com o tipo de componente do Filament,
     * a tradução (FK), a máscara, o tamanho e a aba "Parâmetros".
     *
     * @param  Closure(Get): ?MdTabela  $tabela
     */
    protected static function abaComponentes(Closure $tabela): Tab
    {
        return Tab::make('Componentes')
            ->icon('heroicon-o-squares-plus')
            ->schema([
                Text::make('Componentes da tab "' . MdFormulario::TAB_GERAL . '". Para outras tabs, use a aba Tab pages.'),

                static::repeaterComponentes(
                    Repeater::make('componentes')
                        // grupos ficam para depois; os das tab pages ficam na aba Tab pages
                        ->relationship('componentes', fn ($query) => $query->where('tipo', Layout::CAMPO)->whereNull('md_formulario_pagina_id'))
                        ->rules([fn (Get $get) => static::regraObrigatorios($tabela($get), $get('tabPages'))]),
                    $tabela,
                    fn () => false,
                ),
            ]);
    }

    /**
     * Aba "Tab pages": as tabs do formulário além da "Geral", cada uma com o
     * tipo e os componentes (campos da tabela, campos da tabela pai, lista de
     * outra tabela ou conteúdo customizado).
     *
     * @param  Closure(Get): ?MdTabela  $tabela
     */
    protected static function abaTabPages(Closure $tabela): Tab
    {
        $tipo = fn (Get $get) => TipoTabPage::tryFrom((string) static::valorEnum($get('tipo'))) ?? TipoTabPage::Campos;
        // dentro do item da tab page: a tabela de destino (tipo Tabela)
        $destino = fn (Get $get) => filled($get('tabela_id')) ? MdTabela::with('campos')->find($get('tabela_id')) : null;

        return Tab::make('Tab pages')
            ->icon('heroicon-o-folder')
            ->badge(fn (Get $get) => count($get('tabPages') ?? []) ?: null)
            ->schema([
                Text::make('Os componentes sem tab page aparecem na tab "' . MdFormulario::TAB_GERAL . '". Arraste para mudar a ordem das tabs.'),

                Repeater::make('tabPages')
                    ->hiddenLabel()
                    ->relationship('tabPages')
                    ->orderColumn('ordem')
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar tab page')
                    ->itemLabel(fn (array $state) => (($state['nome'] ?? null) ?: 'Nova tab page') . ' · ' . (TipoTabPage::tryFrom((string) static::valorEnum($state['tipo'] ?? null))?->getLabel() ?? ''))
                    ->schema([
                        Tabs::make('tabpage')->tabs([
                            Tab::make('Geral')
                                ->columns(3)
                                ->schema([
                                    TextInput::make('nome')
                                        ->label('Nome da tab page')
                                        ->required()
                                        ->maxLength(60)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                            if (blank($get('nome_fisico'))) {
                                                $set('nome_fisico', Campos::snake($state));
                                            }
                                        }),

                                    Campos::nomeFisico()
                                        ->distinct()
                                        ->notIn([Str::upper(MdFormulario::TAB_GERAL)])
                                        ->helperText(fn (Get $get) => $tipo($get) === TipoTabPage::Customizada
                                            ? 'Método no Resource: ' . MdFormularioPagina::metodoCustomizado((string) $get('nome_fisico')) . '()'
                                            : null),

                                    Select::make('tipo')
                                        ->options(TipoTabPage::class)
                                        ->default(TipoTabPage::Campos)
                                        ->required()
                                        ->live()
                                        ->selectablePlaceholder(false),

                                    // tipo "Campos da tabela pai"
                                    Select::make('fk_campo')
                                        ->label('Ligação com a tabela pai (FK)')
                                        ->options(fn (Get $get) => static::camposDaTabela($tabela($get))
                                            ->filter(fn (array $c) => $c['fk'])
                                            ->map(fn (array $c) => "{$c['nome']} -> {$c['fk']->nome}")
                                            ->all())
                                        ->helperText('Os campos da tabela pai mostram o registro escolhido nessa FK (só consulta).')
                                        ->required(fn (Get $get) => $tipo($get) === TipoTabPage::Pai)
                                        ->visible(fn (Get $get) => $tipo($get) === TipoTabPage::Pai)
                                        ->live(),

                                    // tipo "Tabela"
                                    Select::make('tabela_id')
                                        ->label('Tabela')
                                        ->options(fn (Get $get) => MdTabela::query()
                                            ->whereNotNull('generated_at')
                                            ->where('banco', $tabela($get)?->banco?->value)
                                            ->orderBy('nome')
                                            ->get()
                                            ->mapWithKeys(fn (MdTabela $t) => [$t->id => "{$t->nome} ({$t->nome_fisico})"])
                                            ->all())
                                        ->searchable()
                                        ->required(fn (Get $get) => $tipo($get) === TipoTabPage::Tabela)
                                        ->visible(fn (Get $get) => $tipo($get) === TipoTabPage::Tabela)
                                        ->live()
                                        ->helperText(function (Get $get) use ($destino, $tabela) {
                                            $alvo = $destino($get);

                                            if ($alvo?->tipoTabela() !== TipoTabela::Ligacao) {
                                                return null;
                                            }

                                            $outro = Ligacao::outroLado($alvo, $tabela($get));

                                            return 'Tabela de ligação: a lista mostra ' . ($outro ? "os registros de {$outro->tabela->nome} ligados a este" : 'o outro lado da ligação (a tabela precisa de uma FK para cada lado)') . '.';
                                        })
                                        // sugere a ligação: a FK da tabela escolhida que aponta para a tabela deste formulário
                                        ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($tabela) {
                                            $fk = Ligacao::fkPara(filled($state) ? MdTabela::with('campos')->find($state) : null, $tabela($get));

                                            $set('campo_tabela', $fk ? MdFormularioPagina::CAMPO_ID : null);
                                            $set('campo_ligacao', $fk?->uid);
                                        }),

                                    Select::make('campo_tabela')
                                        ->label('Campo da tabela')
                                        ->options(fn (Get $get) => static::camposDeLigacao($tabela($get)))
                                        ->placeholder('Sem ligação (todos os registros)')
                                        ->helperText('Campo deste formulário.')
                                        ->requiredWith('campo_ligacao')
                                        ->visible(fn (Get $get) => $tipo($get) === TipoTabPage::Tabela),

                                    Select::make('campo_ligacao')
                                        ->label('Campo de ligação')
                                        ->options(fn (Get $get) => static::camposDeLigacao($destino($get)))
                                        ->placeholder('Sem ligação (todos os registros)')
                                        ->helperText('Campo da tabela de destino que filtra os registros. Sem os dois campos: todos (até 100).')
                                        ->requiredWith('campo_tabela')
                                        ->visible(fn (Get $get) => $tipo($get) === TipoTabPage::Tabela),

                                    Toggle::make('sem_permissao')
                                        ->label('Sem permissão')
                                        ->helperText('Só lista: ninguém inclui, altera ou exclui por esta tab.')
                                        ->inline(false)
                                        ->visible(fn (Get $get) => $tipo($get) === TipoTabPage::Tabela),
                                ]),

                            Tab::make('Componentes')
                                ->schema([
                                    Text::make(fn (Get $get) => match ($tipo($get)) {
                                        TipoTabPage::Campos => 'Campos da tabela deste formulário.',
                                        TipoTabPage::Pai => 'Campos da tabela pai (só consulta). Escolha a ligação (FK) na aba Geral.',
                                        TipoTabPage::Tabela => 'A lista mostra as colunas do gerenciamento da tabela de destino: não há componentes aqui.',
                                        TipoTabPage::Customizada => 'Os campos são incluídos por código, no método ' . MdFormularioPagina::metodoCustomizado((string) $get('nome_fisico')) . '() do Resource.',
                                    }),

                                    static::repeaterComponentes(
                                        Repeater::make('componentes')
                                            ->relationship('componentes')
                                            ->visible(fn (Get $get) => $tipo($get)->temComponentes()),
                                        fn (Get $get) => static::tabelaDaTabPage($get, $tabela),
                                        fn (Get $get) => static::valorEnum($get('../../tipo')) === TipoTabPage::Pai->value,
                                        // o componente também pertence ao formulário (a relação é pela tab page)
                                        fn (array $data, Component $livewire) => [...$data, 'md_formulario_id' => $livewire->getRecord()?->getKey()],
                                    ),
                                ]),
                        ]),
                    ]),
            ]);
    }

    /**
     * Repeater de componentes (usado na tab "Geral" e nas tab pages).
     *
     * @param  Closure(Get): ?MdTabela  $fonte  tabela de onde vêm os campos
     * @param  Closure(Get): bool  $soConsulta  campos da tabela pai: sem tipo de componente, tradução ou máscara
     * @param  (Closure(array, Component): array)|null  $aoCriar  ajuste extra dos dados ao criar
     */
    protected static function repeaterComponentes(Repeater $repeater, Closure $fonte, Closure $soConsulta, ?Closure $aoCriar = null): Repeater
    {
        // dentro do item: o campo escolhido e o tipo de componente
        $campo = fn (Get $get) => static::campoDoComponente($fonte($get), $get('campo'));
        $tipo = fn (Get $get) => TipoComponente::tryFrom((string) $get('componente'));
        $editavel = fn (Get $get) => ! $soConsulta($get);

        return $repeater
            ->hiddenLabel()
            ->orderColumn('ordem')
            ->reorderable()
            ->collapsible()
            ->defaultItems(0)
            ->addActionLabel('Adicionar componente')
            ->itemLabel(function (array $state, Get $get) use ($fonte) {
                $info = static::campoDoComponente($fonte($get), $state['campo'] ?? null);

                return $info ? $info['nome'] . ' · ' . (TipoComponente::tryFrom((string) ($state['componente'] ?? ''))?->getLabel() ?? 'consulta') : 'Novo componente';
            })
            ->mutateRelationshipDataBeforeFillUsing(function (array $data) {
                // sem valor gravado: segue o campo da tabela
                $situacao = $data['campo'] === MdFormulario::CAMPO_SITUACAO;
                $mdCampo = $situacao ? null : MdCampo::where('uid', $data['campo'])->first();

                $data['obrigatorio'] ??= $situacao || (bool) $mdCampo?->obrigatorio;
                $data['componente'] = TipoComponente::resolver($data['componente'] ?? null, $mdCampo?->tipo)->value;

                return $data;
            })
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Component $livewire) => $aoCriar
                ? $aoCriar(static::dadosDoComponente($data), $livewire)
                : static::dadosDoComponente($data))
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => static::dadosDoComponente($data))
            ->schema([
                Tabs::make('componente')
                    ->tabs([
                        Tab::make('Componente')
                            ->columns(3)
                            ->schema([
                                Select::make('campo')
                                    ->label('Campo')
                                    ->options(fn (Get $get) => static::camposDaTabela($fonte($get))
                                        ->reject(fn (array $c, string $uid) => $soConsulta($get) && $uid === MdFormulario::CAMPO_SITUACAO)
                                        ->map(fn ($c) => "{$c['nome']} ({$c['nome_fisico']})")
                                        ->all())
                                    ->required()
                                    ->searchable()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($fonte) {
                                        $info = static::campoDoComponente($fonte($get), $state);

                                        $set('componente', static::componentesAceitos($info)[0]->value ?? null);
                                        $set('obrigatorio', $info['obrigatorio'] ?? false);
                                        $set('traducao_id', null);
                                        $set('mascara', null);
                                    }),

                                Select::make('componente')
                                    ->label('Tipo do componente')
                                    ->options(fn (Get $get) => TipoComponente::opcoes(static::componentesAceitos($campo($get))))
                                    ->required($editavel)
                                    ->visible($editavel)
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        $tipo = TipoComponente::tryFrom((string) $state);

                                        if (! $tipo?->aceitaMascara()) {
                                            $set('mascara', null);
                                        }
                                        if ($tipo !== TipoComponente::Lookup) {
                                            $set('traducao_id', null);
                                        }
                                    }),

                                Select::make('traducao_id')
                                    ->label('Tradução')
                                    ->options(fn (Get $get) => ($fk = $campo($get)['fk'] ?? null) ? Traducao::opcoes($fk, Traducao::LOOKUP) : [])
                                    ->placeholder('Padrão da tabela')
                                    ->visible($editavel)
                                    // só para o componente "Tabela" de um campo FK
                                    ->disabled(fn (Get $get) => $tipo($get) !== TipoComponente::Lookup || ! ($campo($get)['fk'] ?? null)),

                                TextInput::make('mascara')
                                    ->label('Máscara')
                                    ->maxLength(60)
                                    ->datalist(array_keys(TipoComponente::mascaras()))
                                    ->visible($editavel)
                                    ->disabled(fn (Get $get) => ! $tipo($get)?->aceitaMascara()),

                                TextInput::make('largura')
                                    ->label('Largura (px)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4000)
                                    ->placeholder('Automática'),

                                TextInput::make('altura')
                                    ->label('Altura (px)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4000)
                                    ->placeholder('Automática'),

                                TextInput::make('colunas')
                                    ->label('Ocupa (colunas)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4)
                                    ->default(1)
                                    ->required(),
                            ]),

                        Tab::make('Parâmetros')
                            ->columns(3)
                            ->schema([
                                Checkbox::make('quebra')
                                    ->label('Próxima linha')
                                    ->helperText('Começa numa linha nova.'),

                                Checkbox::make('completar')
                                    ->label('Alinhar pela sugestão')
                                    ->helperText('Ocupa até o fim da linha.'),

                                Checkbox::make('obrigatorio')
                                    ->label('Obrigatório')
                                    ->visible($editavel),
                            ]),
                    ]),
            ]);
    }

    /**
     * Dentro de um componente de tab page: a tabela de onde vêm os campos
     * (a do formulário, ou a tabela pai pela FK da tab page).
     *
     * @param  Closure(Get): ?MdTabela  $tabela
     */
    protected static function tabelaDaTabPage(Get $get, Closure $tabela): ?MdTabela
    {
        if (static::valorEnum($get('../../tipo')) !== TipoTabPage::Pai->value) {
            return $tabela($get);
        }

        return MdCampo::with('fkTabela')->where('uid', $get('../../fk_campo'))->first()?->fkTabela;
    }

    /**
     * Campos para ligar tabelas (tipo Tabela): o id e os campos da tabela.
     *
     * @return array<string, string>
     */
    protected static function camposDeLigacao(?MdTabela $tabela): array
    {
        if (! $tabela) {
            return [];
        }

        return [
            MdFormularioPagina::CAMPO_ID => 'ID (chave do registro)',
            ...$tabela->campos()->orderBy('ordem')->get()->mapWithKeys(fn (MdCampo $c) => [$c->uid => "{$c->nome} ({$c->nome_fisico})"])->all(),
        ];
    }

    /** Estado de select com enum: pode vir o enum ou o valor */
    protected static function valorEnum(mixed $valor): mixed
    {
        return $valor instanceof \BackedEnum ? $valor->value : $valor;
    }

    /**
     * Campos do botão (nome, título, nome físico, arquivo, ícone, cor). Usado também nos gerenciamentos.
     *
     * @param  Closure(Get): ?MdTabela  $tabela
     * @param  Closure(Get, Component): list<string>  $outros  botões da tabela fora deste cadastro
     */
    public static function camposBotao(Closure $tabela, Closure $outros, array $extras = []): array
    {
        return [
            TextInput::make('nome')
                ->required()
                ->maxLength(60)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                    if (blank($get('titulo'))) {
                        $set('titulo', $state);
                    }
                    if (blank($get('nome_fisico'))) {
                        $set('nome_fisico', Campos::snake($state));
                    }
                }),

            TextInput::make('titulo')
                ->label('Título (texto do botão)')
                ->required()
                ->maxLength(60),

            Campos::nomeFisico(label: 'Nome físico (método)')
                ->distinct()
                ->rules([Campos::regraBotaoUnico($outros)]),

            Campos::nomeArquivo()
                ->default(fn (Get $get) => $tabela($get)?->classeBotoesPadrao())
                ->placeholder(fn (Get $get) => $tabela($get)?->classeBotoesPadrao())
                ->rules([Campos::regraClasseLivre($tabela, 'botoes')])
                ->helperText('Botões com o mesmo arquivo ficam na mesma classe.'),

            Campos::icone(),

            Campos::cor(),

            ...$extras,

            Toggle::make('confirmar')
                ->label('Pedir confirmação')
                ->inline(false),
        ];
    }

    protected static function sugerir(Get $get, Set $set): void
    {
        $tabela = MdTabela::with('modulo')->find($get('md_tabela_id'));

        if (! $tabela) {
            return;
        }

        foreach (static::sugestoes($tabela, ['nome' => $get('nome'), 'titulo' => $get('titulo'), 'nome_fisico' => $get('nome_fisico'), 'nome_arquivo' => $get('nome_arquivo')]) as $campo => $valor) {
            $set($campo, $valor);
        }
    }

    /**
     * Valores sugeridos para um formulário novo da tabela (mantém o que já foi digitado).
     */
    public static function sugestoes(MdTabela $tabela, array $atual = []): array
    {
        $quantos = MdFormulario::where('md_tabela_id', $tabela->id)->count();
        $nomeFisico = MdFormulario::where('nome_fisico', $tabela->nome_fisico)->exists() ? "{$tabela->nome_fisico}_" . ($quantos + 1) : $tabela->nome_fisico;
        $arquivo = 'View' . $tabela->nomeClasse();

        return [
            'nome' => ($atual['nome'] ?? null) ?: $tabela->nome,
            'titulo' => ($atual['titulo'] ?? null) ?: $tabela->nome,
            'nome_fisico' => ($atual['nome_fisico'] ?? null) ?: $nomeFisico,
            'nome_arquivo' => ($atual['nome_arquivo'] ?? null) ?: (in_array($arquivo, Campos::classesEmUso($tabela, 'view'), true) ? $arquivo . ($quantos + 1) : $arquivo),
            'componentes' => static::componentesPadrao($tabela),
        ];
    }

    /**
     * O que vai para a linha de md_formulario_componentes.
     */
    protected static function dadosDoComponente(array $data): array
    {
        $tipo = TipoComponente::tryFrom((string) ($data['componente'] ?? ''));

        return [
            ...$data,
            'tipo' => Layout::CAMPO,
            'pai_id' => null,
            'traducao_id' => $tipo === TipoComponente::Lookup ? ($data['traducao_id'] ?? null) : null,
            'mascara' => $tipo?->aceitaMascara() ? ($data['mascara'] ?? null) : null,
            'quebra' => (bool) ($data['quebra'] ?? false),
            'completar' => (bool) ($data['completar'] ?? false),
            'obrigatorio' => (bool) ($data['obrigatorio'] ?? false),
        ];
    }

    /**
     * Campos da tabela que podem ter componente (os "sem componente" ficam de fora), por uid.
     *
     * @return Collection<string, array{nome: string, nome_fisico: string, tipo: ?TipoCampo, obrigatorio: bool, fk: ?MdTabela}>
     */
    public static function camposDaTabela(?MdTabela $tabela): Collection
    {
        if (! $tabela) {
            return collect();
        }

        return static::$camposCache[$tabela->id] ??= (function () use ($tabela) {
            $campos = $tabela->campos()->with('fkTabela')->where('sem_componente', false)->get()->mapWithKeys(fn (MdCampo $c) => [$c->uid => [
                'nome' => $c->nome,
                'nome_fisico' => $c->nome_fisico,
                'tipo' => $c->tipo,
                'obrigatorio' => (bool) $c->obrigatorio,
                'fk' => $c->isFk() ? $c->fkTabela : null,
            ]]);

            if ($tabela->controla_situacao) {
                $campos[MdFormulario::CAMPO_SITUACAO] = ['nome' => 'Situação', 'nome_fisico' => MdTabela::COLUNA_SITUACAO, 'tipo' => null, 'obrigatorio' => true, 'fk' => null];
            }

            return $campos;
        })();
    }

    protected static function campoDoComponente(?MdTabela $tabela, ?string $uid): ?array
    {
        return $uid ? static::camposDaTabela($tabela)->get($uid) : null;
    }

    /**
     * Componentes do Filament que o campo aceita (o primeiro é o padrão).
     *
     * @return list<TipoComponente>
     */
    protected static function componentesAceitos(?array $campo): array
    {
        if (! $campo) {
            return [];
        }

        return $campo['tipo'] ? TipoComponente::para($campo['tipo']) : TipoComponente::paraSituacao();
    }

    /**
     * Componentes de um formulário novo: os campos da tabela, na ordem, com o componente padrão.
     */
    public static function componentesPadrao(MdTabela $tabela): array
    {
        $itens = [];

        foreach (MdFormulario::layoutPadrao($tabela) as $pagina) {
            foreach ($pagina['campos'] ?? [] as $elemento) {
                $info = static::campoDoComponente($tabela, $elemento['campo'] ?? null);

                if (($elemento['tipo'] ?? null) !== Layout::CAMPO || ! $info) {
                    continue;
                }

                $itens[(string) Str::uuid()] = [
                    'campo' => $elemento['campo'],
                    'componente' => TipoComponente::resolver($elemento['componente'] ?? null, $info['tipo'])->value,
                    'colunas' => $elemento['colunas'] ?? 1,
                    'completar' => (bool) ($elemento['completar'] ?? false),
                    'quebra' => false,
                    'obrigatorio' => $info['obrigatorio'],
                ];
            }
        }

        return $itens;
    }

    /**
     * Campo obrigatório sem valor padrão fora do formulário: a inclusão falharia no banco.
     */
    protected static function regraObrigatorios(?MdTabela $tabela, ?array $tabPages = []): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($tabela, $tabPages) {
            if (! $tabela) {
                return;
            }

            // campos da tabela: na tab "Geral" e nas tab pages do tipo "Campos da tabela"
            $nasTabPages = collect($tabPages ?? [])
                ->filter(fn ($p) => is_array($p) && static::valorEnum($p['tipo'] ?? null) === TipoTabPage::Campos->value)
                ->flatMap(fn (array $p) => collect($p['componentes'] ?? [])->pluck('campo'))
                ->filter();

            $naGeral = collect($value ?? [])->pluck('campo')->filter();
            $repetidos = $naGeral->merge($nasTabPages)->duplicates()->unique();

            if ($repetidos->isNotEmpty()) {
                $nomes = static::camposDaTabela($tabela)->only($repetidos->all())->pluck('nome');
                $fail('Cada campo aparece em uma tab só. Repetidos: ' . $nomes->join(', ') . '.');

                return;
            }

            $usados = $naGeral->merge($nasTabPages)->all();

            // sem componente: preenchido por baixo dos panos (eventos, view), fica de fora da regra
            $faltam = $tabela->campos()->get()
                ->filter(fn (MdCampo $c) => $c->obrigatorio && blank($c->padrao) && ! $c->sem_componente && ! in_array($c->uid, $usados, true))
                ->pluck('nome');

            if ($faltam->isNotEmpty()) {
                $fail('Campos obrigatórios (sem valor padrão) precisam ter componente: ' . $faltam->join(', ') . '.');
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tabela.modulo')->withCount(['botoes', 'componentes']))
            ->columns([
                TextColumn::make('tabela.modulo.nome')->label('Módulo')->badge(),
                TextColumn::make('tabela.nome_fisico')->label('Tabela')->fontFamily('mono')->searchable(),
                TextColumn::make('titulo')->label('Título')->searchable(),
                TextColumn::make('nome_arquivo')->label('Arquivo')->suffix('.php')->fontFamily('mono'),
                TextColumn::make('componentes_count')->label('Componentes'),
                TextColumn::make('botoes_count')->label('Botões'),
            ])
            ->filters([
                SelectFilter::make('md_tabela_id')->label('Tabela')->relationship('tabela', 'nome'),
            ])
            ->recordActions([
                EditAction::make(),
                static::excluir(DeleteAction::make()),
            ]);
    }

    /**
     * Abre o formulário como ele vai ficar, com o que está na tela agora
     * (inclusive o que ainda não foi salvo). Só visual: nada é gravado.
     */
    public static function previa(): Action
    {
        return Action::make('previa')
            ->label('Pré-visualizar')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (Component $livewire) => 'Prévia: ' . ($livewire->form->getRawState()['titulo'] ?? null ?: 'formulário'))
            ->modalDescription('Assim o formulário vai aparecer para o usuário. Clique com o botão direito num campo para ajustar o layout e a ordem.')
            ->modalWidth(fn (Component $livewire) => match ((int) ($livewire->form->getRawState()['colunas'] ?? 2)) {
                1 => Width::ThreeExtraLarge,
                2 => Width::FiveExtraLarge,
                default => Width::SevenExtraLarge,
            })
            ->disabled(fn (Component $livewire) => blank($livewire->form->getRawState()['md_tabela_id'] ?? null))
            ->tooltip(fn (Component $livewire) => blank($livewire->form->getRawState()['md_tabela_id'] ?? null) ? 'Escolha a tabela primeiro' : null)
            // ajustes começam do que está na aba Componentes (inclusive o que não foi salvo)
            ->mountUsing(fn (Component $livewire) => $livewire->iniciarPrevia())
            // "Salvar" é uma ação à parte: não valida os campos de teste da prévia
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->extraModalFooterActions([
                Action::make('salvarPrevia')
                    ->label('Salvar')
                    ->icon('heroicon-o-check')
                    ->action(fn (Component $livewire) => $livewire->aplicarPrevia())
                    ->cancelParentActions(),
            ])
            // aberto pelo clique direito ($wire.mountAction): por cima da prévia
            ->registerModalActions([
                static::ajustarCampoPrevia(),
            ])
            ->schema(function (Component $livewire): array {
                $estado = $livewire->form->getRawState();
                $tabela = MdTabela::with('modulo')->find($estado['md_tabela_id'] ?? null);

                if (! $tabela) {
                    return [];
                }

                // o formulário salvo (para achar o Resource), ou um em memória ainda não criado
                $formulario = $livewire->record instanceof MdFormulario ? clone $livewire->record : new MdFormulario;
                $formulario->fill(collect($estado)->only(['nome', 'titulo', 'nome_fisico', 'nome_arquivo', 'colunas'])->all());
                $formulario->md_tabela_id = $tabela->id;
                $formulario->setRelation('tabela', $tabela);

                // dados de cada componente (tipo, máscara...) da "Geral" e das tab pages, pelo campo
                $tabPages = $livewire->tabPagesDaTela();
                $itens = collect($estado['componentes'] ?? []);

                foreach (array_keys($tabPages) as $chave) {
                    $itens = $itens->merge(array_values($estado['tabPages'][$chave]['componentes'] ?? []));
                }

                $itens = $itens->filter(fn ($item) => is_array($item) && filled($item['campo'] ?? null));
                $itens = ($itens->isEmpty() ? collect(static::componentesPadrao($tabela)) : $itens)->keyBy(fn (array $item) => (string) $item['campo']);

                $elementos = fn (?string $aba) => collect($livewire->previa)
                    ->filter(fn (array $ajuste) => $ajuste['aba'] === $aba && $itens->has($ajuste['campo']))
                    ->map(fn (array $ajuste) => array_filter([
                        'tipo' => Layout::CAMPO,
                        'campo' => $ajuste['campo'],
                        'componente' => $itens[$ajuste['campo']]['componente'] ?? null,
                        'mascara' => $itens[$ajuste['campo']]['mascara'] ?? null,
                        'traducao_id' => $itens[$ajuste['campo']]['traducao_id'] ?? null,
                        'colunas' => $itens[$ajuste['campo']]['colunas'] ?? null,
                        'obrigatorio' => $itens[$ajuste['campo']]['obrigatorio'] ?? null,
                        'quebra' => $ajuste['quebra'],
                        'completar' => $ajuste['completar'],
                        'largura' => $ajuste['largura'],
                        'altura' => $ajuste['altura'],
                    ], fn ($valor, $chave) => filled($valor) || ($chave === 'obrigatorio' && $valor === false), ARRAY_FILTER_USE_BOTH))
                    ->values()
                    ->all();

                // "Geral" e as tab pages da tela, na ordem do repeater
                $layout = [['titulo' => MdFormulario::TAB_GERAL, 'pagina' => null, 'campos' => $elementos(null)]];

                foreach ($tabPages as $chave => $tabPage) {
                    $layout[] = ['titulo' => $tabPage['nome'] ?: 'Tab page', 'pagina' => $tabPage, 'campos' => $elementos($chave)];
                }

                return [
                    // menu do clique direito (Alpine)
                    View::make('filament.dicionario.previa-designer'),
                    ...FormularioBuilder::previa($formulario, $layout, (int) ($estado['colunas'] ?? 2)),
                ];
            });
    }

    /**
     * "Ajustar campo" (clique direito na prévia): tab page, ordem, largura e altura.
     */
    protected static function ajustarCampoPrevia(): Action
    {
        $nomes = fn (Component $livewire) => MdCampo::whereIn('uid', collect($livewire->previa)->pluck('campo'))
            ->pluck('nome', 'uid')
            ->put(MdFormulario::CAMPO_SITUACAO, 'Situação')
            ->all();

        return Action::make('ajustarPrevia')
            ->modalHeading(fn (array $arguments, Component $livewire) => 'Ajustar campo: ' . ($nomes($livewire)[$arguments['campo'] ?? ''] ?? ''))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Salvar')
            ->fillForm(fn (array $arguments, Component $livewire) => $livewire->previaAjusteAtual((string) ($arguments['campo'] ?? '')))
            ->schema(fn (array $arguments, Component $livewire) => [
                Select::make('aba')
                    ->label('Tab page')
                    ->options($livewire->abasPermitidas((string) ($arguments['campo'] ?? '')))
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('posicao', count($livewire->posicoesNaAba((string) ($arguments['campo'] ?? ''), $state, []))))
                    ->helperText('Campos da tabela: "' . MdFormulario::TAB_GERAL . '" ou tab pages de campos. Campos da tabela pai: tab pages com a mesma ligação.'),

                Select::make('posicao')
                    ->label('Ordem na tab')
                    ->options(fn (Get $get) => $livewire->posicoesNaAba((string) ($arguments['campo'] ?? ''), $get('aba'), $nomes($livewire)))
                    ->required()
                    ->selectablePlaceholder(false)
                    ->helperText('Os outros campos andam uma casa; as quebras de linha ficam nas posições.'),

                Grid::make(2)->schema([
                    TextInput::make('largura')->label('Largura (px)')->numeric()->integer()->minValue(20)->maxValue(2000)->suffix('px')->placeholder('Automática'),
                    TextInput::make('altura')->label('Altura (px)')->numeric()->integer()->minValue(20)->maxValue(2000)->suffix('px')->placeholder('Automática'),
                ]),
            ])
            ->action(fn (array $data, array $arguments, Component $livewire) => $livewire->previaAjustar(
                (string) ($arguments['campo'] ?? ''),
                $data['aba'] ?? null,
                (int) $data['posicao'],
                $data['largura'] ?? null,
                $data['altura'] ?? null,
            ));
    }

    public static function excluir(DeleteAction $action): DeleteAction
    {
        return $action->modalDescription('Remove o formulário do dicionário. O arquivo da classe (com o seu código) é mantido.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMdFormularios::route('/'),
            'create' => Pages\CreateMdFormulario::route('/create'),
            'edit' => Pages\EditMdFormulario::route('/{record}/edit'),
        ];
    }
}
