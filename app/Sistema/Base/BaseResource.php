<?php

namespace App\Sistema\Base;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdFormularioBotao;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdGerenciamentoBotao;
use App\Models\Md\MdTabela;
use App\Sistema\Base\Concerns\Utilidades;
use App\Sistema\Enums\Cor;
use App\Sistema\Permissoes;
use App\Sistema\Registro;
use App\Sistema\Runtime\Acesso;
use App\Sistema\Runtime\ColunasBuilder;
use App\Sistema\Runtime\Executor;
use App\Sistema\Runtime\FiltrosBuilder;
use App\Sistema\Runtime\FormularioBuilder;
use App\Sistema\Runtime\Janela;
use App\Sistema\Runtime\ModuloNavegacao;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Base dos Resources gerados pelo dicionário (um por gerenciamento, ex.:
 * ProdutoResource). Formulário, colunas, filtros e botões vêm do dicionário
 * na hora: mudou lá, muda a tela sem regerar arquivo.
 *
 * Permissões: as do Shield (view_any_op_produto, create_op_produto...),
 * criadas pelo gerador e marcadas por papel em Funções.
 *
 * Para personalizar, sobrescreva os métodos da seção "Personalização" no
 * Resource gerado. Neles, $this->operacao (incluir, alterar, visualizar) e
 * $this->registro dizem em que tela está, e $this->abortar('...'),
 * $this->notificar(...), $this->getModelDe('op', 'produto') estão disponíveis.
 */
abstract class BaseResource extends Resource
{
    // getModel() já é do Filament (model do Resource): o de qualquer módulo vira getModelDe('op', 'produto')
    use Utilidades {
        Utilidades::getModel as getModelDe;
    }

    public const INCLUIR = 'incluir';

    public const ALTERAR = 'alterar';

    public const VISUALIZAR = 'visualizar';

    /** Nome físico do gerenciamento no dicionário (definido no Resource gerado) */
    protected static string $gerenciamento;

    /** Contexto da tela, nos métodos de personalização */
    public string $operacao = self::INCLUIR;

    public ?Model $registro = null;

    // ------------------------------------------------------------------
    // Personalização (sobrescreva no Resource gerado)
    // ------------------------------------------------------------------

    /** Formulário inteiro somente leitura. Padrão: só na visualização. */
    public function getReadOnly(): bool
    {
        return $this->visualizando();
    }

    /** @return list<string> campos (nome físico) somente leitura */
    public function getCamposSomenteLeitura(): array
    {
        return [];
    }

    /** @return list<string> campos (nome físico) que não aparecem no formulário */
    public function getCamposOcultos(): array
    {
        return [];
    }

    /** Personaliza o componente de um campo (máscara, regra, live(), etc.). */
    public function personalizarCampo(string $nomeFisico, Component $componente): Component
    {
        return $componente;
    }

    /** Dados do registro antes de preencher a tela. */
    public function antesDePreencher(array $dados): array
    {
        return $dados;
    }

    /** Dados da tela antes de gravar (já validados). Pode alterar ou chamar $this->abortar(). */
    public function antesDeSalvar(array $dados): array
    {
        return $dados;
    }

    /** Depois de gravar (ainda dentro da transação). */
    public function depoisDeSalvar(Model $registro): void {}

    /** Filtra/ordena os registros da listagem. */
    public function query(Builder $query): Builder
    {
        return $query;
    }

    /** Personaliza a coluna de um campo (nome físico). */
    public function personalizarColuna(string $nomeFisico, Column $coluna): Column
    {
        return $coluna;
    }

    /** @return list<BaseFilter> filtros extras, além dos do dicionário */
    public function filtros(): array
    {
        return [];
    }

    /** Regras próprias além da permissão do papel (ex.: não alterar pedido faturado). */
    public function podeIncluir(): bool
    {
        return true;
    }

    public function podeAlterar(Model $registro): bool
    {
        return true;
    }

    public function podeExcluir(Model $registro): bool
    {
        return true;
    }

    /** Botão (nome físico) visível? $registro vem nos botões de registro e no formulário. */
    public function botaoVisivel(string $nomeFisico, ?Model $registro = null): bool
    {
        return true;
    }

    // ------------------------------------------------------------------
    // Contexto
    // ------------------------------------------------------------------

    /**
     * Instância para os métodos de personalização, no contexto da tela.
     */
    public static function tela(string $operacao = self::INCLUIR, ?Model $registro = null): static
    {
        $tela = app(static::class);
        $tela->operacao = $operacao;
        $tela->registro = $registro;

        return $tela;
    }

    public function incluindo(): bool
    {
        return $this->operacao === self::INCLUIR;
    }

    public function alterando(): bool
    {
        return $this->operacao === self::ALTERAR;
    }

    public function visualizando(): bool
    {
        return $this->operacao === self::VISUALIZAR;
    }

    /** Operação do Filament (create, edit, view) -> operação do sistema */
    public static function operacaoDo(?string $operacaoFilament): string
    {
        return match ($operacaoFilament) {
            'edit' => self::ALTERAR,
            'view' => self::VISUALIZAR,
            default => self::INCLUIR,
        };
    }

    // ------------------------------------------------------------------
    // Dicionário
    // ------------------------------------------------------------------

    public static function md(): MdGerenciamento
    {
        return Registro::gerenciamento(static::$gerenciamento);
    }

    public static function formulario(): ?MdFormulario
    {
        $md = static::md();
        $formulario = $md->formulario ?? $md->tabela->formularioPadrao();
        $formulario?->setRelation('tabela', $md->tabela);

        return $formulario;
    }

    public static function getModel(): string
    {
        return static::md()->tabela->modelFqcn();
    }

    public static function getModelLabel(): string
    {
        return static::formulario()?->titulo ?? static::md()->tabela->nome;
    }

    public static function getPluralModelLabel(): string
    {
        return static::md()->titulo;
    }

    public static function getNavigationLabel(): string
    {
        return static::md()->titulo;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::md()->tabela->modulo->nome;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return ModuloNavegacao::icone(static::md());
    }

    public static function getNavigationSort(): ?int
    {
        return static::md()->ordem;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return static::md()->tabela->campo_exibicao;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'm/'.Str::lower(static::$gerenciamento);
    }

    // ------------------------------------------------------------------
    // Permissões (Shield)
    // ------------------------------------------------------------------

    /** Ações com permissão: as do Shield, na ordem da tela de Funções */
    public const ACOES = ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny'];

    /** Sujeito das permissões: view_any_{sujeito}, create_{sujeito}... */
    public static function sujeitoPermissao(): string
    {
        return Str::lower(static::$gerenciamento);
    }

    public static function permissao(string $acao): string
    {
        return MdGerenciamento::permissaoDo(static::$gerenciamento, $acao);
    }

    /**
     * Módulo liberado para o papel + permissão do Shield. Lista e conteúdo
     * são cadastrados no dicionário: aqui só se consulta.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof BackedEnum ? (string) $action->value : ($action instanceof UnitEnum ? $action->name : $action);

        if (! in_array($action, static::ACOES, true) || ! Acesso::modulo(static::md()->tabela->modulo)) {
            return Response::deny();
        }

        $escrita = ! in_array($action, ['viewAny', 'view'], true);

        if ($escrita && ! static::md()->tabela->tipoTabela()->editavel()) {
            return Response::deny();
        }

        if ($escrita && $action === 'create' && ! static::formulario()) {
            return Response::deny();
        }

        if (! Permissoes::pode(static::permissao($action))) {
            return Response::deny();
        }

        $tela = static::tela(match ($action) {
            'create' => self::INCLUIR,
            'update' => self::ALTERAR,
            default => self::VISUALIZAR,
        }, $record);

        $permitido = match ($action) {
            'create' => $tela->podeIncluir(),
            'update' => $record ? $tela->podeAlterar($record) : true,
            'delete' => $record ? $tela->podeExcluir($record) : true,
            default => true,
        };

        return $permitido ? Response::allow() : Response::deny();
    }

    // ------------------------------------------------------------------
    // Formulário
    // ------------------------------------------------------------------

    public static function form(Schema $schema): Schema
    {
        $formulario = static::formulario();

        if (! $formulario) {
            return $schema;
        }

        $tela = static::tela(static::operacaoDo($schema->getOperation()), $schema->getRecord());

        return $schema
            ->columns(1)
            // um cartão (fundo e bordas arredondadas) como o da listagem
            ->components([Section::make()->schema((new FormularioBuilder($formulario, $tela))->componentes())->columnSpanFull()])
            ->disabled($tela->getReadOnly());
    }

    /**
     * Botões do formulário (dicionário), no topo das telas de incluir, alterar e visualizar, em ordem e com grupos.
     *
     * @return list<Action|ActionGroup>
     */
    public static function botoesDoFormulario(?Model $registro, \Livewire\Component $livewire): array
    {
        $formulario = static::formulario();
        $tabela = static::md()->tabela;
        $tela = static::tela(registro: $registro);

        if (! $formulario) {
            return [];
        }

        return static::agruparBotoes($formulario->botoes, $formulario->grupos_botoes, fn ($b) => Action::make($b->nome_fisico)
            ->label($b->titulo)
            ->icon($b->icone)
            ->color(Cor::resolver($b->cor))
            ->requiresConfirmation($b->confirmar)
            ->visible(fn () => $tela->botaoVisivel($b->nome_fisico, $registro))
            ->action(fn () => Executor::botao(
                Registro::botoes($tabela, $b->nome_arquivo),
                $registro ?? Registro::model($tabela),
                $b->nome_fisico,
                $b->titulo,
                $livewire,
                [$registro, $livewire->data ?? []],
            )));
    }

    /**
     * Botões na ordem da tela: os sem grupo e os grupos (um botão com setinha que abre os botões dele) se
     * misturam pelo campo "ordem"; dentro do grupo, os botões seguem a ordem deles.
     *
     * @param  iterable<MdFormularioBotao|MdGerenciamentoBotao>  $botoes
     * @param  list<array{nome?: string, titulo?: string, icone?: string, cor?: string, ordem?: int}>|null  $grupos
     * @param  Closure(MdFormularioBotao|MdGerenciamentoBotao): Action  $acao
     * @return list<Action|ActionGroup>
     */
    public static function agruparBotoes(iterable $botoes, ?array $grupos, Closure $acao): array
    {
        $grupos = collect($grupos ?? [])->filter(fn ($g) => filled($g['nome'] ?? null))->keyBy('nome');
        $itens = [];

        foreach (collect($botoes)->sortBy([['ordem', 'asc'], ['id', 'asc']])->groupBy(fn ($b) => $grupos->has($b->grupo) ? $b->grupo : '') as $grupo => $doGrupo) {
            if ($grupo === '') {
                foreach ($doGrupo as $b) {
                    $itens[] = [(int) $b->ordem, $acao($b)];
                }

                continue;
            }

            $definicao = $grupos->get($grupo);
            $acoes = $doGrupo->map($acao)->values()->all();
            $soDeSelecao = collect($acoes)->every(fn ($a) => $a instanceof BulkAction);

            $itens[] = [(int) ($definicao['ordem'] ?? 0), ($soDeSelecao ? BulkActionGroup::make($acoes) : ActionGroup::make($acoes))
                ->label(($definicao['titulo'] ?? null) ?: $grupo)
                ->icon(($definicao['icone'] ?? null) ?: 'heroicon-m-chevron-down')
                ->color(Cor::resolver(($definicao['cor'] ?? null) ?: 'gray'))
                ->button()];
        }

        return collect($itens)->sortBy(fn ($item) => $item[0])->map(fn ($item) => $item[1])->values()->all();
    }

    // ------------------------------------------------------------------
    // Listagem
    // ------------------------------------------------------------------

    public static function getEloquentQuery(): Builder
    {
        $tabela = static::md()->tabela;
        $relacoes = $tabela->campos->filter(fn ($c) => $c->isFk() && $c->fkTabela)->map(fn ($c) => $c->nomeRelacao())->values()->all();

        $consulta = parent::getEloquentQuery()->with($relacoes);

        // aberta pela seta de um campo de FK: a lista mostra só o registro apontado, para editar
        // (chave inteira só aceita dígitos: texto no lugar estouraria no PostgreSQL)
        if (Janela::recurso() === static::sujeitoPermissao() && filled($id = Janela::filtroId())
            && ($consulta->getModel()->getKeyType() !== 'int' || ctype_digit($id))) {
            $consulta->whereKey($id);
        }

        return static::tela()->query($consulta);
    }

    public static function table(Table $table): Table
    {
        $md = static::md();
        $tabela = $md->tabela;
        $tela = static::tela(self::VISUALIZAR);
        $model = Registro::model($tabela);

        $construtor = new FiltrosBuilder($md);
        $filtros = [...$construtor->filtros(), ...$tela->filtros()];

        if ($tabela->controla_situacao && ! $construtor->temFiltroDeSituacao()) {
            $filtros[] = SelectFilter::make(MdTabela::COLUNA_SITUACAO)
                ->label('Situação')
                ->multiple()
                ->options($tabela->situacoes->filter(fn ($s) => $s->status)->mapWithKeys(fn ($s) => [$s->status->id => $s->status->nome])->all());
        }

        return $table
            ->columns((new ColunasBuilder($md, $tela))->colunas())
            ->filters($filtros)
            ->defaultSort($model->getKeyName(), 'desc')
            // na lista de seleção (lupa de um campo de FK) a linha não abre o registro: o duplo clique seleciona
            ->recordUrl(fn (Model $record) => static::emSelecao() ? null : static::urlDoRegistro($record))
            // nenhum botão na linha: todos ficam no topo e agem nos registros selecionados
            ->recordActions([])
            ->toolbarActions([
                BulkAction::make('alterar')
                    ->label('Alterar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->visible(fn () => static::formulario() && ! static::emSelecao())
                    ->action(function (Collection $records, $livewire) {
                        if (! static::umSelecionado($records, 'Alterar')) {
                            return;
                        }

                        if (! static::canEdit($registro = $records->first())) {
                            Notification::make()->title('Você não pode alterar este registro')->warning()->send();

                            return;
                        }

                        $livewire->redirect(static::getUrl('edit', ['record' => $registro]));
                    }),

                ...static::agruparBotoes($md->botoes, $md->grupos_botoes, fn (MdGerenciamentoBotao $b) => static::botao($b, $tela, $tabela, $model)),

                BulkAction::make('excluir_selecionados')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => static::canDeleteAny() && ! static::emSelecao())
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        $excluidos = static::excluir($records->filter(fn (Model $r) => static::canDelete($r)));

                        if ($excluidos) {
                            Notification::make()->title($excluidos === 1 ? 'Registro excluído' : "{$excluidos} registros excluídos")->success()->send();
                        }
                    }),
            ]);
    }

    /**
     * Aberta pela lupa de um campo de FK (escolher um registro e devolver ao campo).
     */
    public static function emSelecao(): bool
    {
        return Janela::modo() === 'selecionar' && Janela::recurso() === static::sujeitoPermissao() && filled(Janela::campo());
    }

    protected static function urlDoRegistro(Model $record): ?string
    {
        return match (true) {
            static::formulario() && static::canEdit($record) => static::getUrl('edit', ['record' => $record]),
            static::canView($record) => static::getUrl('view', ['record' => $record]),
            default => null,
        };
    }

    /**
     * Ações que valem para um registro só: avisa se nenhum ou mais de um foi selecionado.
     */
    protected static function umSelecionado(Collection $records, string $acao): bool
    {
        if ($records->count() === 1) {
            return true;
        }

        Notification::make()->title("{$acao}: selecione um registro")->warning()->send();

        return false;
    }

    /**
     * Botão do gerenciamento no topo do datagrid: sem seleção é uma ação comum; com seleção age nos marcados
     * (vários se for multioperação, senão exatamente um).
     */
    protected static function botao(MdGerenciamentoBotao $b, self $tela, MdTabela $tabela, Model $model): Action
    {
        $acao = $b->exige_selecao ? BulkAction::make($b->nome_fisico) : Action::make($b->nome_fisico);

        $acao->label($b->titulo)
            ->icon($b->icone)
            ->color(Cor::resolver($b->cor))
            ->requiresConfirmation($b->confirmar)
            ->visible(fn () => $tela->botaoVisivel($b->nome_fisico) && ! static::emSelecao());

        if (! $b->exige_selecao) {
            return $acao->action(fn ($livewire) => Executor::botao(Registro::botoes($tabela, $b->nome_arquivo), $model, $b->nome_fisico, $b->titulo, $livewire));
        }

        return $acao
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, $livewire) use ($b, $tela, $tabela, $model) {
                if (! $b->multiplos && ! static::umSelecionado($records, $b->titulo)) {
                    return;
                }

                // a regra de visibilidade do botão (botaoVisivel) vale para cada registro selecionado
                $permitidos = $records->filter(fn (Model $registro) => $tela->botaoVisivel($b->nome_fisico, $registro))->values();

                if ($permitidos->count() < $records->count()) {
                    $fora = $records->count() - $permitidos->count();
                    Notification::make()->title("{$b->titulo}: não vale para {$fora} ".($fora === 1 ? 'registro selecionado' : 'registros selecionados'))->warning()->send();
                }

                if ($permitidos->isEmpty()) {
                    return;
                }

                $b->multiplos
                    ? Executor::botao(Registro::botoes($tabela, $b->nome_arquivo), $model, $b->nome_fisico, $b->titulo, $livewire, [$permitidos])
                    : Executor::botao(Registro::botoes($tabela, $b->nome_arquivo), $permitidos->first(), $b->nome_fisico, $b->titulo, $livewire, [$permitidos->first()]);
            });
    }

    /**
     * Exclui numa transação só (as regras beforeDelete/afterDelete do controller rodam por registro).
     *
     * @return int registros excluídos (0 se a exclusão foi interrompida)
     */
    public static function excluir(Collection $registros): int
    {
        if ($registros->isEmpty()) {
            return 0;
        }

        $controller = Registro::controller(static::md()->tabela);

        return (int) Executor::transacao($registros->first(), function () use ($controller, $registros) {
            foreach ($registros as $registro) {
                $controller->excluir($registro);
            }

            return $registros->count();
        });
    }

    // ------------------------------------------------------------------
    // Páginas (geradas junto com o Resource, na pasta {Resource}/Pages)
    // ------------------------------------------------------------------

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        $pages = static::class.'\\Pages\\';

        return [
            'index' => ($pages.'Listar')::route('/'),
            'create' => ($pages.'Criar')::route('/create'),
            'view' => ($pages.'Visualizar')::route('/{record}'),
            'edit' => ($pages.'Alterar')::route('/{record}/edit'),
        ];
    }
}
