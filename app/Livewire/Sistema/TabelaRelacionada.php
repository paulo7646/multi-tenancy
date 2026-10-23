<?php

namespace App\Livewire\Sistema;

use App\Models\Md\MdFormulario;
use App\Models\Md\MdFormularioPagina;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Sistema\Base\BaseResource;
use App\Sistema\Enums\TipoTabela;
use App\Sistema\Permissoes;
use App\Sistema\Registro;
use App\Sistema\Runtime\ColunasBuilder;
use App\Sistema\Runtime\Executor;
use App\Sistema\Runtime\FormularioBuilder;
use App\Sistema\Runtime\Ligacao;
use App\Sistema\Runtime\Traducao;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * Tab page do tipo "Tabela".
 *
 * Tabela comum: lista os registros da tabela de destino ligados ao registro
 * do formulário (destino.campo_ligacao = registro.campo_tabela), com incluir,
 * alterar e excluir. Sem campos de ligação: todos os registros.
 *
 * Tabela de ligação (N:N, ex. PRODUTO_PEDIDO numa tab do Pedido): lista os
 * registros do outro lado (os produtos), com "Vincular" (existentes),
 * "Incluir" (novo produto já ligado), "Alterar" (o produto) e "Excluir"
 * (só a ligação).
 *
 * Sempre no máximo 100 registros. "Sem permissão" na tab page: só lista.
 * Permissões: as do Resource (Shield) da tabela listada; ligar e desligar
 * contam como alterar o registro aberto (permissão da tela de origem).
 */
class TabelaRelacionada extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public const LIMITE = 100;

    public int $tabPageId;

    public mixed $registroId = null;

    // ------------------------------------------------------------------
    // Dicionário
    // ------------------------------------------------------------------

    public function tabPage(): MdFormularioPagina
    {
        return once(fn () => MdFormularioPagina::with(['tabela.modulo', 'tabela.campos.fkTabela', 'tabela.situacoes.status', 'formulario.tabela.campos'])->findOrFail($this->tabPageId));
    }

    /** Tabela escolhida na tab page (a de ligação, quando for o caso) */
    public function destino(): MdTabela
    {
        return $this->tabPage()->tabela ?? abort(404, 'Tab page sem tabela de destino.');
    }

    /** Tabela do formulário aberto */
    public function origem(): MdTabela
    {
        return $this->tabPage()->formulario->tabela;
    }

    public function ehLigacao(): bool
    {
        return $this->destino()->tipoTabela() === TipoTabela::Ligacao;
    }

    /**
     * Tabela de ligação: a FK do outro lado e a tabela dela (os registros listados).
     *
     * @return object{campo: \App\Models\Md\MdCampo, tabela: MdTabela}|null
     */
    public function outroLado(): ?object
    {
        return once(fn () => $this->ehLigacao()
            ? Ligacao::outroLado($this->destino(), $this->origem(), $this->tabPage()->campo_ligacao)
            : null);
    }

    /** Tabela cujos registros aparecem na lista */
    public function listada(): MdTabela
    {
        if (! $this->ehLigacao()) {
            return $this->destino();
        }

        $tabela = $this->outroLado()?->tabela ?? abort(500, "A tabela de ligação {$this->destino()->nome_fisico} precisa de uma FK para cada lado.");
        $tabela->loadMissing(['modulo', 'campos.fkTabela', 'situacoes.status']);

        return $tabela;
    }

    /** Formulário para incluir/alterar a tabela listada (o padrão dela) */
    public function formularioListada(): ?MdFormulario
    {
        return once(function () {
            $formulario = $this->listada()->formularioPadrao();
            $formulario?->setRelation('tabela', $this->listada());

            return $formulario;
        });
    }

    /**
     * Gerenciamento da tabela listada: colunas da lista e o Resource das
     * permissões. Sem gerenciamento, um em memória com as colunas padrão.
     */
    public function gerenciamentoListada(): MdGerenciamento
    {
        return once(function () {
            $gerenciamento = $this->listada()->gerenciamentos()->orderBy('id')->first() ?? new MdGerenciamento;
            $gerenciamento->setRelation('tabela', $this->listada());

            return $gerenciamento;
        });
    }

    /** @return class-string<BaseResource>|null */
    public function resourceListada(): ?string
    {
        $gerenciamento = $this->gerenciamentoListada();

        return $gerenciamento->exists ? Registro::resource($gerenciamento) : null;
    }

    /** Personalização da tabela listada (hooks do Resource dela, ou a base) */
    protected function tela(string $operacao, ?Model $registro = null): BaseResource
    {
        return Registro::tela($this->formularioListada() ?? new MdFormulario, $operacao, $registro);
    }

    // ------------------------------------------------------------------
    // Ligação
    // ------------------------------------------------------------------

    /**
     * Campo (na tabela escolhida) que liga ao registro aberto. Na tabela de
     * ligação, sem configuração, é a FK dela que aponta para a origem.
     */
    public function campoLigacao(): ?string
    {
        $tabPage = $this->tabPage();

        if ($this->ehLigacao() && blank($tabPage->campo_ligacao)) {
            return Ligacao::fkPara($this->destino(), $this->origem())?->nome_fisico;
        }

        if (blank($tabPage->campo_tabela) || blank($tabPage->campo_ligacao)) {
            return null;
        }

        if ($tabPage->campo_ligacao === MdFormularioPagina::CAMPO_ID) {
            return Registro::model($this->destino())->getKeyName();
        }

        return $this->destino()->campos->firstWhere('uid', $tabPage->campo_ligacao)?->nome_fisico;
    }

    /**
     * Valor do campo da tabela do formulário no registro aberto (null = registro ainda não salvo).
     */
    public function valorLigacao(): mixed
    {
        return once(function () {
            $tabPage = $this->tabPage();

            if (blank($this->registroId)) {
                return null;
            }

            // ligação sem configuração: pelo id do registro aberto
            if ($tabPage->campo_tabela === MdFormularioPagina::CAMPO_ID || ($this->ehLigacao() && blank($tabPage->campo_tabela))) {
                return $this->registroId;
            }

            $campo = $this->origem()->campos->firstWhere('uid', $tabPage->campo_tabela);

            return $campo ? $this->registroOrigem()?->getAttribute($campo->nome_fisico) : null;
        });
    }

    public function registroOrigem(): ?Model
    {
        return once(fn () => blank($this->registroId) ? null : Registro::model($this->origem())->newQuery()->find($this->registroId));
    }

    /** Ligação configurada, mas sem valor (registro novo): a lista fica vazia */
    public function aguardandoRegistro(): bool
    {
        return ($this->ehLigacao() || $this->campoLigacao() !== null) && blank($this->valorLigacao());
    }

    /** Tabela de ligação: query das linhas de ligação do registro aberto */
    protected function ligacoes(): Builder
    {
        return Registro::model($this->destino())->newQuery()->where($this->campoLigacao(), $this->valorLigacao());
    }

    // ------------------------------------------------------------------
    // Permissões
    // ------------------------------------------------------------------

    /**
     * @param  string  $acao  viewAny | create | update | delete | vincular
     */
    protected function pode(string $acao, ?Model $registro = null): bool
    {
        if ($acao !== 'viewAny' && $this->tabPage()->sem_permissao) {
            return false;
        }

        if ($acao === 'viewAny') {
            return $this->podeNoResource($this->resourceListada(), 'viewAny');
        }

        if ($this->ehLigacao()) {
            // ligar/desligar = alterar o registro aberto; incluir/alterar = permissões da tabela listada
            $ligar = $this->podeNoResource($this->resourceOrigem(), 'update', $this->registroOrigem());

            return match ($acao) {
                'vincular', 'delete' => $ligar,
                'create' => $ligar && $this->listada()->tipoTabela()->editavel() && (bool) $this->formularioListada() && $this->podeNoResource($this->resourceListada(), 'create'),
                'update' => $this->listada()->tipoTabela()->editavel() && (bool) $this->formularioListada() && $registro && $this->podeNoResource($this->resourceListada(), 'update', $registro),
                default => false,
            };
        }

        if (! $this->destino()->tipoTabela()->editavel()) {
            return false;
        }

        return match ($acao) {
            'create' => (bool) $this->formularioListada() && $this->podeNoResource($this->resourceListada(), 'create'),
            'update' => (bool) $this->formularioListada() && $registro && $this->podeNoResource($this->resourceListada(), 'update', $registro),
            'delete' => $registro && $this->podeNoResource($this->resourceListada(), 'delete', $registro),
            default => false,
        };
    }

    /** Sem tela própria (Resource), só o super_admin */
    protected function podeNoResource(?string $resource, string $acao, ?Model $registro = null): bool
    {
        if (! $resource) {
            return (bool) auth()->user()?->hasRole(Permissoes::superAdmin());
        }

        return match ($acao) {
            'create' => $resource::canCreate(),
            'update' => $registro ? $resource::canEdit($registro) : false,
            'delete' => $registro ? $resource::canDelete($registro) : false,
            default => $resource::canViewAny(),
        };
    }

    /** Resource da tela do formulário aberto (para ligar/desligar) */
    protected function resourceOrigem(): ?string
    {
        return once(function () {
            $formulario = $this->tabPage()->formulario;
            $gerenciamento = MdGerenciamento::with('tabela.modulo')->where('md_formulario_id', $formulario->id)->orderBy('id')->first()
                ?? MdGerenciamento::with('tabela.modulo')->where('md_tabela_id', $formulario->md_tabela_id)->orderBy('id')->first();

            return $gerenciamento ? Registro::resource($gerenciamento) : null;
        });
    }

    // ------------------------------------------------------------------
    // Tabela
    // ------------------------------------------------------------------

    public function table(Table $table): Table
    {
        $listada = $this->listada();
        $model = Registro::model($listada);

        return $table
            ->query(fn (): Builder => $this->consulta($model))
            ->heading($this->tabPage()->nome)
            ->description(fn () => match (true) {
                $this->aguardandoRegistro() => 'Salve o registro para ver e incluir os itens ligados a ele.',
                (bool) $this->tabPage()->sem_permissao => 'Somente consulta.',
                default => 'Até ' . static::LIMITE . ' registros de ' . $listada->nome . '.',
            })
            ->columns((new ColunasBuilder($this->gerenciamentoListada(), $this->tela(BaseResource::VISUALIZAR)))->colunas())
            ->defaultSort($model->getKeyName(), 'desc')
            ->paginated(false)
            ->emptyStateHeading('Nenhum registro')
            ->headerActions([
                $this->acaoVincular(),

                CreateAction::make()
                    ->label('Incluir')
                    ->model($model::class)
                    ->modalHeading('Incluir ' . $listada->nome)
                    ->visible(fn () => ! $this->aguardandoRegistro() && $this->pode('create'))
                    ->schema(fn () => $this->formulario(BaseResource::INCLUIR))
                    ->fillForm(fn () => $this->ehLigacao() ? [] : $this->comLigacao([]))
                    ->using(fn (array $data) => $this->gravar(Registro::model($listada), $data, BaseResource::INCLUIR)),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Alterar')
                    ->iconButton()
                    ->visible(fn (Model $record) => $this->pode('update', $record))
                    ->schema(fn (Model $record) => $this->formulario(BaseResource::ALTERAR, $record))
                    ->using(fn (Model $record, array $data) => $this->gravar($record, $data, BaseResource::ALTERAR)),

                DeleteAction::make()
                    ->label($this->ehLigacao() ? 'Remover' : 'Excluir')
                    ->iconButton()
                    ->modalHeading(fn (Model $record) => $this->ehLigacao() ? 'Remover a ligação?' : 'Excluir o registro?')
                    ->modalDescription($this->ehLigacao() ? 'Só a ligação com este registro é removida; o ' . mb_strtolower($listada->nome) . ' continua existindo.' : null)
                    ->visible(fn (Model $record) => $this->pode('delete', $record))
                    ->using(fn (Model $record) => $this->excluir($record)),
            ]);
    }

    protected function consulta(Model $model): Builder
    {
        $query = $model->newQuery()->limit(static::LIMITE);

        if ($this->aguardandoRegistro()) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->ehLigacao()) {
            // os registros do outro lado que têm linha de ligação com o registro aberto
            return $query->whereIn($model->getKeyName(), $this->ligacoes()->select($this->outroLado()->campo->nome_fisico));
        }

        $campoLigacao = $this->campoLigacao();

        return $campoLigacao ? $query->where($campoLigacao, $this->valorLigacao()) : $query;
    }

    /**
     * Tabela de ligação: "Vincular" registros que já existem (cria só as linhas de ligação).
     */
    protected function acaoVincular(): Action
    {
        return Action::make('vincular')
            ->label('Vincular')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->modalHeading(fn () => 'Vincular ' . mb_strtolower($this->listada()->nome))
            ->modalSubmitActionLabel('Vincular')
            ->visible(fn () => $this->ehLigacao() && ! $this->aguardandoRegistro() && $this->pode('vincular'))
            ->schema(fn () => [
                Select::make('registros')
                    ->label($this->listada()->nome)
                    ->multiple()
                    ->required()
                    ->searchable()
                    // os que ainda não estão ligados a este registro, pela tradução de lookup
                    ->getSearchResultsUsing(fn (?string $search) => $this->opcoesParaVincular($search))
                    ->options(fn () => $this->opcoesParaVincular())
                    ->getOptionLabelsUsing(fn (array $values) => Registro::model($this->listada())->newQuery()->findMany($values)
                        ->mapWithKeys(fn (Model $r) => [$r->getKey() => Traducao::texto($r, $this->listada(), Traducao::LOOKUP)])
                        ->all()),
            ])
            ->action(function (array $data) {
                $ligados = $this->vincular((array) ($data['registros'] ?? []));

                if ($ligados) {
                    Notification::make()->title($ligados === 1 ? '1 registro vinculado' : "{$ligados} registros vinculados")->success()->send();
                }
            });
    }

    /** @return array<int|string, string> */
    protected function opcoesParaVincular(?string $search = null): array
    {
        $listada = $this->listada();
        $model = Registro::model($listada);

        return Traducao::ordenar($model->newQuery(), $listada, Traducao::LOOKUP)
            ->whereNotIn($model->getKeyName(), $this->ligacoes()->select($this->outroLado()->campo->nome_fisico))
            ->when(filled($search), fn ($q) => Traducao::buscar($q, $listada, Traducao::LOOKUP, $search))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $r) => [$r->getKey() => Traducao::texto($r, $listada, Traducao::LOOKUP)])
            ->all();
    }

    /**
     * Cria as linhas de ligação (eventos do model da tabela de ligação), numa transação.
     *
     * @param  list<int|string>  $ids
     */
    protected function vincular(array $ids): int
    {
        $outro = $this->outroLado()->campo->nome_fisico;
        $ligacao = Registro::model($this->destino());

        return (int) Executor::transacao($ligacao, function () use ($ids, $outro, $ligacao) {
            $criados = 0;

            foreach (array_unique($ids) as $id) {
                if ($this->ligacoes()->where($outro, $id)->exists()) {
                    continue;
                }

                $linha = $ligacao->newInstance([$this->campoLigacao() => $this->valorLigacao(), $outro => $id]);

                if ($linha->save() === false) {
                    $this->tela(BaseResource::INCLUIR)->abortar('A ligação foi cancelada pelas regras da tabela.');
                }

                $criados++;
            }

            return $criados;
        });
    }

    /**
     * Excluir: na tabela de ligação, só as linhas que ligam o registro ao aberto.
     */
    protected function excluir(Model $record): bool
    {
        if (! $this->ehLigacao()) {
            return (bool) Executor::transacao($record, function () use ($record) {
                if ($record->delete() === false) {
                    $this->tela(BaseResource::VISUALIZAR, $record)->abortar('A exclusão foi cancelada pelas regras da tabela.');
                }

                return true;
            });
        }

        $outro = $this->outroLado()->campo->nome_fisico;

        return (bool) Executor::transacao(Registro::model($this->destino()), function () use ($record, $outro) {
            foreach ($this->ligacoes()->where($outro, $record->getKey())->get() as $linha) {
                if ($linha->delete() === false) {
                    $this->tela(BaseResource::VISUALIZAR, $record)->abortar('A remoção da ligação foi cancelada pelas regras da tabela.');
                }
            }

            return true;
        });
    }

    /**
     * Campos do formulário da tabela listada (na tabela comum, o campo de
     * ligação fica travado: vem do registro aberto).
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    protected function formulario(string $operacao, ?Model $registro = null): array
    {
        $formulario = $this->formularioListada();

        if (! $formulario) {
            return [];
        }

        $tela = $this->tela($operacao, $registro);
        $campoLigacao = $this->ehLigacao() ? null : $this->campoLigacao();

        // trava o campo de ligação sem mexer no Resource da tabela listada
        $travada = new class($tela, $campoLigacao) extends BaseResource
        {
            public function __construct(private BaseResource $base, private ?string $campoLigacao)
            {
                $this->operacao = $base->operacao;
                $this->registro = $base->registro;
            }

            public function getCamposSomenteLeitura(): array
            {
                return [...$this->base->getCamposSomenteLeitura(), ...array_filter([$this->campoLigacao])];
            }

            public function getCamposOcultos(): array
            {
                return $this->base->getCamposOcultos();
            }

            public function personalizarCampo(string $nomeFisico, \Filament\Schemas\Components\Component $componente): \Filament\Schemas\Components\Component
            {
                return $this->base->personalizarCampo($nomeFisico, $componente);
            }
        };

        return (new FormularioBuilder($formulario, $travada, profundidade: 1))->componentes();
    }

    protected function comLigacao(array $dados): array
    {
        $campoLigacao = $this->ehLigacao() ? null : $this->campoLigacao();

        return $campoLigacao ? [...$dados, $campoLigacao => $this->valorLigacao()] : $dados;
    }

    /**
     * Grava na tabela listada com os eventos do model e os hooks do Resource
     * dela, numa transação. Na tabela de ligação, incluir também cria a ligação.
     */
    protected function gravar(Model $registro, array $dados, string $operacao): Model
    {
        $tela = $this->tela($operacao, $registro->exists ? $registro : null);
        // tabela comum: o campo de ligação vem sempre do registro aberto (ele fica travado na tela)
        $dados = $this->comLigacao($dados);
        $novo = ! $registro->exists;

        $salvo = Executor::transacao($registro, function () use ($tela, $registro, $dados, $novo) {
            $registro->fill($tela->antesDeSalvar($dados));

            if ($registro->save() === false) {
                $tela->abortar('A gravação foi cancelada pelas regras da tabela.');
            }

            $tela->depoisDeSalvar($registro);

            if ($novo && $this->ehLigacao()) {
                $this->vincular([$registro->getKey()]);
            }

            return true;
        });

        if (! $salvo) {
            throw new Halt;
        }

        return $registro;
    }

    public function render(): View
    {
        return view('livewire.sistema.tabela-relacionada');
    }
}
