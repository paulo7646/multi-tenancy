<?php

namespace App\Sistema\Base;

use App\Models\Md\MdFormulario;
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
use App\Sistema\Runtime\ModuloNavegacao;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
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

    /** @return list<\Filament\Tables\Filters\BaseFilter> filtros extras, além dos do dicionário */
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

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return static::md()->tabela->modulo->nome;
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
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
        return 'm/' . Str::lower(static::$gerenciamento);
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
    public static function getAuthorizationResponse(string | UnitEnum $action, ?Model $record = null): Response
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
            ->components((new FormularioBuilder($formulario, $tela))->componentes())
            ->disabled($tela->getReadOnly());
    }

    /**
     * Botões do formulário (dicionário), no topo das telas de incluir, alterar e visualizar.
     *
     * @return list<Action>
     */
    public static function botoesDoFormulario(?Model $registro, \Livewire\Component $livewire): array
    {
        $formulario = static::formulario();
        $tabela = static::md()->tabela;
        $tela = static::tela(registro: $registro);

        return $formulario?->botoes->map(fn ($b) => Action::make($b->nome_fisico)
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
            )))->values()->all() ?? [];
    }

    // ------------------------------------------------------------------
    // Listagem
    // ------------------------------------------------------------------

    public static function getEloquentQuery(): Builder
    {
        $tabela = static::md()->tabela;
        $relacoes = $tabela->campos->filter(fn ($c) => $c->isFk() && $c->fkTabela)->map(fn ($c) => $c->nomeRelacao())->values()->all();

        return static::tela()->query(parent::getEloquentQuery()->with($relacoes));
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
                ->options($tabela->situacoes->filter(fn ($s) => $s->status)->mapWithKeys(fn ($s) => [$s->status->nome_fisico => $s->status->nome])->all());
        }

        return $table
            ->columns((new ColunasBuilder($md, $tela))->colunas())
            ->filters($filtros)
            ->defaultSort($model->getKeyName(), 'desc')
            ->recordActions([
                ...$md->botoes->where('no_registro', true)->map(fn (MdGerenciamentoBotao $b) => static::botao($b)
                    ->visible(fn (Model $record) => $tela->botaoVisivel($b->nome_fisico, $record))
                    ->action(fn (Model $record, $livewire) => Executor::botao(Registro::botoes($tabela, $b->nome_arquivo), $record, $b->nome_fisico, $b->titulo, $livewire, [$record])))->values()->all(),

                EditAction::make()->iconButton()->visible(fn (Model $record) => static::formulario() && static::canEdit($record)),

                DeleteAction::make()
                    ->iconButton()
                    ->using(fn (Model $record) => static::excluir(collect([$record])) > 0),
            ])
            ->toolbarActions([
                ...$md->botoes->where('no_registro', false)->where('exige_selecao', true)->map(fn (MdGerenciamentoBotao $b) => BulkAction::make($b->nome_fisico)
                    ->label($b->titulo)
                    ->icon($b->icone)
                    ->color(Cor::resolver($b->cor))
                    ->requiresConfirmation($b->confirmar)
                    ->visible(fn () => $tela->botaoVisivel($b->nome_fisico))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, $livewire) use ($b, $tabela, $model) {
                        if (! $b->multiplos && $records->count() > 1) {
                            Notification::make()->title("{$b->titulo}: selecione apenas um registro")->warning()->send();

                            return;
                        }

                        Executor::botao(Registro::botoes($tabela, $b->nome_arquivo), $model, $b->nome_fisico, $b->titulo, $livewire, [$records]);
                    }))->values()->all(),

                BulkAction::make('excluir_selecionados')
                    ->label('Excluir selecionados')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => static::canDeleteAny())
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
     * Botões do topo da listagem que não exigem seleção.
     *
     * @return list<Action>
     */
    public static function botoesDoTopo(\Livewire\Component $livewire): array
    {
        $md = static::md();
        $tabela = $md->tabela;
        $tela = static::tela(self::VISUALIZAR);
        $model = Registro::model($tabela);

        return $md->botoes->where('no_registro', false)->where('exige_selecao', false)->map(fn (MdGerenciamentoBotao $b) => static::botao($b)
            ->visible(fn () => $tela->botaoVisivel($b->nome_fisico))
            ->action(fn () => Executor::botao(Registro::botoes($tabela, $b->nome_arquivo), $model, $b->nome_fisico, $b->titulo, $livewire)))->values()->all();
    }

    protected static function botao(MdGerenciamentoBotao $b): Action
    {
        return Action::make($b->nome_fisico)
            ->label($b->titulo)
            ->icon($b->icone)
            ->color(Cor::resolver($b->cor))
            ->requiresConfirmation($b->confirmar);
    }

    /**
     * Exclui numa transação só (os eventos beforeDelete/afterDelete do model rodam por registro).
     *
     * @return int registros excluídos (0 se a exclusão foi interrompida)
     */
    public static function excluir(Collection $registros): int
    {
        if ($registros->isEmpty()) {
            return 0;
        }

        return (int) Executor::transacao($registros->first(), function () use ($registros) {
            foreach ($registros as $registro) {
                if ($registro->delete() === false) {
                    static::tela()->abortar('A exclusão foi cancelada pelas regras da tabela.');
                }
            }

            return $registros->count();
        });
    }

    // ------------------------------------------------------------------
    // Páginas (geradas junto com o Resource, na pasta {Resource}/Pages)
    // ------------------------------------------------------------------

    public static function getPages(): array
    {
        $pages = static::class . '\\Pages\\';

        return [
            'index' => ($pages . 'Listar')::route('/'),
            'create' => ($pages . 'Criar')::route('/create'),
            'view' => ($pages . 'Visualizar')::route('/{record}'),
            'edit' => ($pages . 'Alterar')::route('/{record}/edit'),
        ];
    }
}
