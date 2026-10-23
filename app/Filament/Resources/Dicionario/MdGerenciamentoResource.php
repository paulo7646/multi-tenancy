<?php

namespace App\Filament\Resources\Dicionario;

use App\Filament\Resources\Dicionario\Concerns\AcessoDicionario;
use App\Filament\Resources\Dicionario\MdGerenciamentoResource\Pages;
use App\Sistema\Registro;
use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Runtime\ColunasBuilder;
use App\Sistema\Runtime\FiltrosBuilder;
use App\Sistema\Runtime\Traducao;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Component;

/**
 * Gerenciamentos: o datagrid inicial de cada tabela (item do menu dentro do
 * módulo), com colunas, botões e o Resource do Filament que vira a tela (ProdutoResource.php).
 */
class MdGerenciamentoResource extends Resource
{
    use AcessoDicionario;

    protected static ?string $model = MdGerenciamento::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $modelLabel = 'Gerenciamento';

    protected static ?string $pluralModelLabel = 'Gerenciamentos';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'dicionario/gerenciamentos';

    public static function form(Schema $schema): Schema
    {
        // dentro dos repeaters o Get é relativo ao item
        $tabela = fn (Get $get) => MdTabela::with('modulo')->find($get('md_tabela_id') ?? $get('../../md_tabela_id'));

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('gerenciamento')
                    ->tabs([
                        Tab::make('Geral')
                            ->icon('heroicon-o-table-cells')
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
        
                                Select::make('md_formulario_id')
                                    ->label('Formulário')
                                    ->options(fn (Get $get) => MdFormulario::where('md_tabela_id', $get('md_tabela_id'))->pluck('titulo', 'id')->all())
                                    ->helperText('Usado para incluir, alterar e visualizar.'),
        
                                TextInput::make('nome')->required()->maxLength(60),
        
                                TextInput::make('titulo')
                                    ->label('Título')
                                    ->required()
                                    ->maxLength(100)
                                    ->helperText('Nome no menu e no topo da listagem.'),
        
                                Campos::nomeFisico()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Endereço da tela: /admin/m/{nome físico}.')
                                    ->disabledOn('edit'),
        
                                Campos::nomeArquivo('Classe do Resource')
                                    ->regex('/^[A-Z][A-Za-z0-9_]*Resource$/')
                                    ->validationMessages(['regex' => 'Nome de classe terminando em Resource (ex.: ProdutoResource).'])
                                    ->rules([Campos::regraClasseLivre($tabela, 'gerenciamento', fn (Component $livewire) => $livewire->record?->id)])
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('md_tabela_id', $get('md_tabela_id')))
                                    ->helperText('Resource do Filament da tela (permissões no Shield). Fica fixo depois de criar.')
                                    ->disabledOn('edit'),
        
                                Campos::icone()->helperText('Sem ícone aqui, o menu usa o ícone (e a cor) da tabela.'),
        
                                TextInput::make('ordem')
                                    ->numeric()
                                    ->integer()
                                    ->default(0)
                                    ->helperText('Posição dentro do módulo no menu.'),
                            ]),

                        Tab::make('Colunas')
                            ->icon('heroicon-o-view-columns')
                            ->schema([
                                Repeater::make('colunas')
                                    ->hiddenLabel()
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->addActionLabel('Adicionar coluna')
                                    ->helperText('Sem colunas: todos os campos da tabela.')
                                    ->columns(5)
                                    ->schema([
                                        Select::make('campo')
                                            ->options(fn (Get $get) => static::opcoesDeColuna($tabela($get)))
                                            ->required()
                                            ->live()
                                            ->distinct(),
                                        // FK: qual tradução de tabela mostrar (sem escolher, a padrão)
                                        Select::make('traducao')
                                            ->label('Tradução')
                                            ->placeholder('Padrão')
                                            ->options(fn (Get $get) => Traducao::opcoes($tabela($get)?->campos()->where('uid', $get('campo'))->first()?->fkTabela, Traducao::TABELA))
                                            ->visible(fn (Get $get) => (bool) $tabela($get)?->campos()->where('uid', $get('campo'))->first()?->isFk()),
                                        Toggle::make('pesquisavel')->label('Pesquisável')->inline(false),
                                        Toggle::make('ordenavel')->label('Ordenável')->inline(false),
                                        Toggle::make('oculta')->label('Oculta (o usuário pode exibir)')->inline(false),
                                    ]),
                            ]),

                        Tab::make('Filtros')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Repeater::make('filtros')
                                    ->hiddenLabel()
                                    ->defaultItems(0)
                                    ->reorderable()
                                    ->addActionLabel('Adicionar filtro')
                                    ->itemLabel(fn (array $state) => $state['nome'] ?? 'Novo filtro')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do filtro')
                                            ->required()
                                            ->maxLength(60),
                                        Select::make('campo')
                                            ->options(fn (Get $get) => static::opcoesDeColuna($tabela($get)))
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($tabela) {
                                                if (blank($get('nome')) && filled($state)) {
                                                    $set('nome', static::opcoesDeColuna($tabela($get), false)[$state] ?? null);
                                                }
                                            })
                                            ->helperText(fn (Get $get) => static::comoFiltra($tabela($get), $get('campo'))),
                                    ]),
                            ]),

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
                                    ->itemLabel(fn (array $state) => ($state['titulo'] ?? 'Novo botão') . ' · ' . static::ondeFica($state))
                                    ->columns(4)
                                    ->schema(MdFormularioResource::camposBotao(
                                        $tabela,
                                        fn (Get $get, Component $livewire) => Campos::botoesDaTabela($tabela($get), ignorarGerenciamento: $livewire->record?->id ?? 0),
                                        [
                                            Toggle::make('no_registro')
                                                ->label('No registro (coluna de ações)')
                                                ->helperText('Desmarcado: fica no topo do datagrid.')
                                                ->default(true)
                                                ->inline(false)
                                                ->live(),

                                            Toggle::make('exige_selecao')
                                                ->label('Exige selecionar registros')
                                                ->inline(false)
                                                ->live()
                                                ->visible(fn (Get $get) => ! $get('no_registro'))
                                                ->dehydrateStateUsing(fn ($state, Get $get) => ! $get('no_registro') && $state),

                                            Toggle::make('multiplos')
                                                ->label('Aceita vários registros')
                                                ->inline(false)
                                                ->visible(fn (Get $get) => ! $get('no_registro') && $get('exige_selecao'))
                                                ->dehydrateStateUsing(fn ($state, Get $get) => ! $get('no_registro') && $get('exige_selecao') && $state),
                                        ],
                                    )),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ]);
    }

    protected static function ondeFica(array $state): string
    {
        return match (true) {
            (bool) ($state['no_registro'] ?? true) => 'no registro',
            (bool) ($state['exige_selecao'] ?? false) && (bool) ($state['multiplos'] ?? false) => 'topo, um ou mais selecionados',
            (bool) ($state['exige_selecao'] ?? false) => 'topo, um selecionado',
            default => 'topo',
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function opcoesDeColuna(?MdTabela $tabela, bool $comNomeFisico = true): array
    {
        if (! $tabela) {
            return [];
        }

        $opcoes = $tabela->campos()->get()->mapWithKeys(fn (MdCampo $c) => [$c->uid => $comNomeFisico ? "{$c->nome} ({$c->nome_fisico})" : $c->nome])->all();

        if ($tabela->controla_situacao) {
            $opcoes[MdFormulario::CAMPO_SITUACAO] = $comNomeFisico ? 'Situação (situacao)' : 'Situação';
        }

        return $opcoes;
    }

    /**
     * Explica o filtro que o tipo do campo vai gerar.
     */
    protected static function comoFiltra(?MdTabela $tabela, ?string $uid): ?string
    {
        if (! $tabela || blank($uid)) {
            return null;
        }

        if ($uid === MdFormulario::CAMPO_SITUACAO) {
            return 'Combo com as situações (pode marcar várias).';
        }

        $tipo = $tabela->campos()->where('uid', $uid)->first()?->tipo;

        return match (true) {
            ! $tipo => null,
            $tipo->isForeign() => 'Combo com até ' . FiltrosBuilder::LIMITE_FK . ' registros da tabela apontada, pesquisável pela tradução de lookup.',
            $tipo->isDate() || $tipo->isDateTime() => 'Datas "de" e "até". Sem "de": desde 01/01/1900. Sem "até": até hoje.',
            $tipo === TipoCampo::Boolean => 'Combo Sim / Não.',
            $tipo->hasChoices() => 'Combo com as opções do campo (pode marcar várias).',
            $tipo->isNumeric() => 'Valores "de" e "até".',
            $tipo->isJson() || $tipo->isUpload() || $tipo === TipoCampo::Password => 'Este tipo de campo não tem filtro.',
            default => 'Texto: registros que contêm o que for digitado.',
        };
    }

    protected static function sugerir(Get $get, Set $set): void
    {
        $tabela = MdTabela::with('modulo')->find($get('md_tabela_id'));

        if (! $tabela) {
            return;
        }

        foreach (static::sugestoes($tabela, ['nome' => $get('nome'), 'titulo' => $get('titulo'), 'nome_fisico' => $get('nome_fisico'), 'nome_arquivo' => $get('nome_arquivo'), 'md_formulario_id' => $get('md_formulario_id')]) as $campo => $valor) {
            $set($campo, $valor);
        }
    }

    /**
     * Valores sugeridos para um gerenciamento novo da tabela (mantém o que já foi digitado).
     */
    public static function sugestoes(MdTabela $tabela, array $atual = []): array
    {
        $quantos = MdGerenciamento::where('md_tabela_id', $tabela->id)->count();
        $arquivo = $tabela->nomeClasse() . 'Resource';

        return [
            'nome' => ($atual['nome'] ?? null) ?: $tabela->nome,
            'titulo' => ($atual['titulo'] ?? null) ?: $tabela->nome,
            'nome_fisico' => ($atual['nome_fisico'] ?? null) ?: (MdGerenciamento::where('nome_fisico', $tabela->nome_fisico)->exists() ? "{$tabela->nome_fisico}_" . ($quantos + 1) : $tabela->nome_fisico),
            'nome_arquivo' => ($atual['nome_arquivo'] ?? null) ?: (in_array($arquivo, Campos::classesEmUso($tabela, 'gerenciamento'), true) ? $arquivo . ($quantos + 1) : $arquivo),
            'md_formulario_id' => ($atual['md_formulario_id'] ?? null) ?: $tabela->formularios()->value('id'),
            'colunas' => ColunasBuilder::padrao($tabela->campos()->get(), $tabela->controla_situacao),
        ];
    }

    /**
     * Abre o datagrid (só os da base pai: os dos clientes abrem no domínio de cada empresa).
     */
    public static function acaoAbrir(): Action
    {
        return Action::make('abrir')
            ->label('Abrir tela')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->visible(fn (?MdGerenciamento $record) => $record?->tabela?->banco === Banco::Central && Registro::resource($record))
            ->url(fn (MdGerenciamento $record) => Registro::resource($record)::getUrl('index', panel: 'admin'))
            ->openUrlInNewTab();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['tabela.modulo', 'formulario'])->withCount('botoes'))
            ->columns([
                TextColumn::make('tabela.modulo.nome')->label('Módulo')->badge(),
                TextColumn::make('tabela.nome_fisico')->label('Tabela')->fontFamily('mono')->searchable(),
                TextColumn::make('titulo')->label('Título')->searchable(),
                TextColumn::make('nome_arquivo')->label('Arquivo')->suffix('.php')->fontFamily('mono'),
                TextColumn::make('formulario.titulo')->label('Formulário')->placeholder('sem formulário'),
                TextColumn::make('botoes_count')->label('Botões'),
            ])
            ->filters([
                SelectFilter::make('md_tabela_id')->label('Tabela')->relationship('tabela', 'nome'),
            ])
            ->recordActions([
                static::acaoAbrir(),
                EditAction::make(),
                static::excluir(DeleteAction::make()),
            ]);
    }

    public static function excluir(DeleteAction $action): DeleteAction
    {
        return $action
            ->modalDescription('Remove o gerenciamento do dicionário (e do menu) e as permissões dele. O arquivo da classe (com o seu código) é mantido.')
            ->before(fn (MdGerenciamento $record) => \App\Sistema\Geradores\Gerador::excluirGerenciamento($record->loadMissing('tabela.modulo')));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMdGerenciamentos::route('/'),
            'create' => Pages\CreateMdGerenciamento::route('/create'),
            'edit' => Pages\EditMdGerenciamento::route('/{record}/edit'),
        ];
    }
}
