<?php

namespace App\Sistema\Runtime;

use App\Livewire\Sistema\TabelaRelacionada;
use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdFormularioPagina;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoTabPage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;
use App\Sistema\Base\BaseResource;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Enums\TipoComponente;
use App\Sistema\Registro;
use App\Sistema\Rules\Cnpj;
use App\Sistema\Rules\Cpf;
use App\Sistema\Schema\SchemaSnapshot;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Monta os componentes do Filament de um formulário a partir dos componentes
 * do dicionário (md_formulario_componentes: tipo do componente, tradução,
 * parâmetros) e do Resource do gerenciamento (personalização).
 *
 * Chave estrangeira: F3 com o campo vazio abre o cadastro da tabela
 * apontada (e já devolve o registro criado); F4 com o campo preenchido abre
 * a visualização do registro.
 */
class FormularioBuilder
{
    /** @var Collection<string, MdCampo> campos da tabela por uid */
    protected Collection $campos;

    public function __construct(
        protected MdFormulario $formulario,
        protected BaseResource $view,
        protected int $profundidade = 0,
        protected ?array $layout = null,
        protected ?int $colunas = null,
        protected bool $previa = false,
    ) {
        $tabela = $formulario->tabela;
        $tabela->loadMissing(['campos.fkTabela.modulo', 'situacoes.status', 'modulo']);
        $this->campos = $tabela->campos->keyBy('uid');
    }

    /**
     * Para a prévia do designer: os componentes, as tab pages e as opções ainda não salvos.
     */
    public static function previa(MdFormulario $formulario, array $layout, int $colunas): array
    {
        $view = Registro::tela($formulario);

        return (new static($formulario, $view, layout: $layout, colunas: $colunas, previa: true))->componentes();
    }

    /**
     * Tab "Geral" (componentes sem tab page) e as tab pages. Só com a "Geral", sem tabs.
     *
     * @return list<Component>
     */
    public function componentes(): array
    {
        $layout = $this->layout ?? ($this->formulario->exists ? $this->formulario->layout() : []);

        $temTabPages = collect($layout)->contains(fn ($p) => filled($p['pagina'] ?? null));

        if (! $temTabPages && Layout::uids($layout) === [] && ! collect($layout)->contains(fn ($p) => ! empty($p['campos']))) {
            $layout = MdFormulario::layoutPadrao($this->formulario->tabela);
        }

        $colunas = max(1, min(4, $this->colunas ?? $this->formulario->colunas ?: 2));

        $paginas = collect($layout)
            ->map(fn (array $pagina) => [
                'titulo' => ($pagina['titulo'] ?? null) ?: MdFormulario::TAB_GERAL,
                'componentes' => $this->pagina($pagina, $colunas),
            ])
            ->filter(fn (array $pagina) => $pagina['componentes'] !== [])
            ->values();

        if ($temTabPages && $paginas->isNotEmpty()) {
            return [
                Tabs::make('paginas')
                    ->tabs($paginas->map(fn (array $pagina) => Tab::make($pagina['titulo'])
                        ->columns($colunas)
                        ->schema($pagina['componentes']))->all())
                    ->columnSpanFull(),
            ];
        }

        return [
            Grid::make($colunas)
                ->schema($paginas->pluck('componentes')->flatten(1)->all())
                ->columnSpanFull(),
        ];
    }

    /**
     * Componentes de uma página: "Geral"/campos da tabela, campos da tabela pai,
     * lista de outra tabela ou conteúdo customizado (método do Resource).
     *
     * @return list<Component>
     */
    protected function pagina(array $pagina, int $colunas): array
    {
        $definicao = is_array($pagina['pagina'] ?? null) ? MdFormulario::definicaoTabPage($pagina['pagina']) : null;
        $tipo = TipoTabPage::tryFrom((string) ($definicao['tipo'] ?? '')) ?? TipoTabPage::Campos;
        $elementos = collect(Layout::elementos($pagina['campos'] ?? []));

        return match ($tipo) {
            TipoTabPage::Campos => $elementos->map(fn (array $elemento) => $this->elemento($elemento, $colunas))->filter()->values()->all(),
            TipoTabPage::Pai => $elementos->map(fn (array $elemento) => $this->elementoPai($definicao, $elemento, $colunas))->filter()->values()->all(),
            TipoTabPage::Tabela => [$this->tabelaRelacionada($definicao)],
            TipoTabPage::Customizada => $this->customizada($definicao),
        };
    }

    /**
     * Tipo "Campos da tabela pai": o campo da tabela apontada pela FK, só para
     * consulta. Acompanha a FK na tela (troca o registro, troca o valor).
     */
    protected function elementoPai(array $definicao, array $elemento, int $colunas): ?Component
    {
        $fk = $this->campos->get((string) $definicao['fk_campo']);
        $pai = $fk?->fkTabela;
        $pai?->loadMissing('campos');
        $campo = $pai?->campos->firstWhere('uid', $elemento['campo'] ?? null);

        if (! $fk || ! $campo) {
            return null;
        }

        $componente = TextEntry::make('tabpage_' . Str::lower($definicao['nome_fisico']) . '_' . Str::lower($campo->nome_fisico))
            ->label($campo->nome)
            ->placeholder('—');

        if ($this->previa) {
            // na prévia (painel central) a tabela pai pode ser de clientes: não consulta dados
            $componente->state("({$pai->nome})")->extraAttributes(['data-previa-campo' => (string) $campo->uid], merge: true);
        } else {
            $componente->state(fn (Get $get) => static::valorDoPai($pai, $get($fk->nome_fisico), $campo));
        }

        return $this->posicionar($componente, $elemento, $colunas);
    }

    /** @var array<string, ?Model> registros pai já lidos nesta requisição */
    protected static array $registrosPai = [];

    protected static function valorDoPai(MdTabela $pai, mixed $id, MdCampo $campo): mixed
    {
        if (blank($id)) {
            return null;
        }

        $registro = static::$registrosPai["{$pai->id}:{$id}"] ??= Registro::model($pai)->newQuery()->find($id);
        $valor = $registro?->getAttribute($campo->nome_fisico);

        return match (true) {
            $valor === null => null,
            $campo->tipo === TipoCampo::Boolean => $valor ? 'Sim' : 'Não',
            $campo->tipo->isDate() && $valor instanceof \DateTimeInterface => $valor->format('d/m/Y'),
            $campo->tipo->isDateTime() && $valor instanceof \DateTimeInterface => $valor->format('d/m/Y H:i'),
            $campo->isFk() && $campo->fkTabela => Traducao::texto($registro->{$campo->nomeRelacao()}, $campo->fkTabela, Traducao::LOOKUP),
            is_array($valor) => implode(', ', $valor),
            default => $valor,
        };
    }

    /**
     * Tipo "Tabela": lista da tabela de destino (incluir, alterar, excluir),
     * ligada pelos campos. Componente Livewire próprio (TabelaRelacionada).
     */
    protected function tabelaRelacionada(array $definicao): Component
    {
        $destino = MdTabela::find($definicao['tabela_id']);

        // tabela de destino excluída do dicionário (ou ainda não escolhida)
        if (! $destino) {
            return Text::make('Esta tab page não tem tabela: escolha a tabela no cadastro do formulário (aba Tab pages).')
                ->columnSpanFull();
        }

        if ($this->previa || ! $definicao['id']) {
            return Text::make('Lista de registros de ' . ($destino?->nome ?? '(tabela não escolhida)') . ', com incluir, alterar e excluir.')
                ->columnSpanFull();
        }

        return Livewire::make(TabelaRelacionada::class, fn () => [
            'tabPageId' => $definicao['id'],
            'registroId' => $this->view->registro?->getKey(),
        ])
            ->key('tabpage-' . Str::lower($definicao['nome_fisico']))
            ->columnSpanFull();
    }

    /**
     * Tipo "Customizada": componentes do método tabpage{NomeFisico}() do Resource.
     *
     * @return list<Component>
     */
    protected function customizada(array $definicao): array
    {
        $metodo = MdFormularioPagina::metodoCustomizado($definicao['nome_fisico']);

        if (! $this->previa && method_exists($this->view, $metodo)) {
            $componentes = array_values($this->view->{$metodo}());

            if ($componentes !== []) {
                return $componentes;
            }
        }

        return [
            Text::make($this->previa ? "Conteúdo feito por código: {$metodo}() no Resource." : "Implemente o método {$metodo}() no Resource da tela.")
                ->columnSpanFull(),
        ];
    }

    /**
     * Um elemento (campo ou grupo) já posicionado na grade.
     */
    protected function elemento(array $elemento, int $colunas): ?Component
    {
        $componente = $elemento['tipo'] === Layout::GRUPO ? $this->grupo($elemento) : $this->campo($elemento);

        if (! $componente) {
            return null;
        }

        // prévia do designer: identifica o campo para o clique direito
        if ($this->previa && $componente instanceof Field && filled($elemento['campo'] ?? null)) {
            $componente->extraFieldWrapperAttributes(['data-previa-campo' => (string) $elemento['campo']], merge: true);
        }

        return $this->posicionar($componente, $elemento, $colunas);
    }

    /**
     * Grupo: um quadro com colunas próprias e os campos dentro.
     */
    protected function grupo(array $elemento): ?Component
    {
        $colunas = max(1, min(4, (int) ($elemento['colunasGrupo'] ?? 2)));

        $filhos = collect(Layout::elementos($elemento['campos'] ?? []))
            ->reject(fn (array $filho) => $filho['tipo'] === Layout::GRUPO)
            ->map(fn (array $filho) => $this->elemento($filho, $colunas))
            ->filter()
            ->values()
            ->all();

        if ($filhos === []) {
            return null;
        }

        $titulo = filled($elemento['titulo'] ?? null) ? $elemento['titulo'] : null;

        return match (TipoComponente::resolverGrupo($elemento['componente'] ?? null)) {
            TipoComponente::Moldura => Fieldset::make($titulo ?? '')->columns($colunas)->schema($filhos),
            TipoComponente::Grade => Grid::make($colunas)->schema($filhos),
            default => Section::make($titulo)->columns($colunas)->compact()->schema($filhos),
        };
    }

    /**
     * Largura em colunas, próxima linha, "alinhar pela sugestão" (até o fim da linha) e tamanho em pixels.
     */
    protected function posicionar(Component $componente, array $elemento, int $colunas): Component
    {
        // columnSpan grava no breakpoint lg e columnSpanFull no default: não misturar os dois
        ! empty($elemento['completar'])
            ? $componente->columnSpanFull()
            : $componente->columnSpan(min(max(1, (int) ($elemento['colunas'] ?? 1)), $colunas));

        if (! empty($elemento['quebra'])) {
            $componente->columnStart(1);
        }

        $largura = Layout::pixels($elemento['largura'] ?? null);
        $altura = Layout::pixels($elemento['altura'] ?? null);

        if ($componente instanceof Field) {
            if ($largura) {
                $componente->extraFieldWrapperAttributes(['style' => "width: {$largura}px; max-width: 100%;"], merge: true);
            }

            if ($altura && method_exists($componente, 'extraInputAttributes')) {
                $componente->extraInputAttributes(['style' => "height: {$altura}px;"], merge: true);
            }
        } elseif ($largura || $altura) {
            $componente->extraAttributes(['style' => ($largura ? "width: {$largura}px; max-width: 100%; " : '') . ($altura ? "min-height: {$altura}px;" : '')], merge: true);
        }

        return $componente;
    }

    protected function campo(array $elemento): ?Component
    {
        $uid = (string) ($elemento['campo'] ?? '');

        if ($uid === MdFormulario::CAMPO_SITUACAO) {
            return $this->formulario->tabela->controla_situacao
                ? $this->aplicarView(MdTabela::COLUNA_SITUACAO, $this->situacao($elemento['componente'] ?? null))
                : null;
        }

        $campo = $this->campos->get($uid);

        // campo sem componente: existe na tabela, mas não vai para o formulário
        if (! $campo || $campo->sem_componente || in_array($campo->nome_fisico, $this->view->getCamposOcultos(), true)) {
            return null;
        }

        $obrigatorio = isset($elemento['obrigatorio']) && $elemento['obrigatorio'] !== '' && $elemento['obrigatorio'] !== null
            ? (bool) $elemento['obrigatorio']
            : null;

        $componente = $this->componente(
            $campo,
            $elemento['componente'] ?? null,
            isset($elemento['traducao_id']) ? (int) $elemento['traducao_id'] : null,
            $obrigatorio,
            $elemento['mascara'] ?? null,
        );

        if (in_array($campo->nome_fisico, $this->view->getCamposSomenteLeitura(), true)) {
            $componente->disabled()->dehydrated(false);
        }

        return $this->aplicarView($campo->nome_fisico, $componente);
    }

    protected function aplicarView(string $nomeFisico, Component $componente): Component
    {
        return $this->view->personalizarCampo($nomeFisico, $componente);
    }

    protected function situacao(?string $componente): Component
    {
        $tabela = $this->formulario->tabela;
        $situacoes = $tabela->situacoes->filter(fn ($s) => $s->status);
        $opcoes = $situacoes->mapWithKeys(fn ($s) => [$s->status->id => $s->status->nome])->all();

        $campo = match (TipoComponente::resolver($componente, null)) {
            TipoComponente::Combo => Select::make(MdTabela::COLUNA_SITUACAO)->options($opcoes)->native(false),
            TipoComponente::Radio => Radio::make(MdTabela::COLUNA_SITUACAO)->options($opcoes)->inline(),
            default => ToggleButtons::make(MdTabela::COLUNA_SITUACAO)
                ->options($opcoes)
                ->icons($situacoes->mapWithKeys(fn ($s) => [$s->status->id => $s->status->icone])->filter()->all())
                ->colors($situacoes->mapWithKeys(fn ($s) => [$s->status->id => $s->status->corFilament()])->all())
                ->inline(),
        };

        return $campo->label('Situação')->default($tabela->situacaoPadrao())->required();
    }

    /**
     * O componente do Filament de um campo, conforme o tipo de componente escolhido.
     *
     * @param  bool|null  $obrigatorio  null = como no campo da tabela
     */
    public function componente(MdCampo $campo, ?string $tipoComponente = null, ?int $traducaoId = null, ?bool $obrigatorio = null, ?string $mascara = null): Field
    {
        $tipo = $campo->tipo;
        $nome = $campo->nome_fisico;
        $tamanho = $campo->tamanho ?: $tipo->defaultLength();
        $pasta = 'md/' . $this->formulario->tabela->nome_fisico;
        $comp = TipoComponente::resolver($tipoComponente, $tipo);

        $componente = match ($comp) {
            TipoComponente::Lookup => $this->fk($campo, $traducaoId),
            TipoComponente::Combo => Select::make($nome)->options($this->opcoes($campo)),
            TipoComponente::Radio => Radio::make($nome)->options($this->opcoes($campo))->inline(),
            TipoComponente::Botoes => ToggleButtons::make($nome)->options($this->opcoes($campo))->inline(),
            TipoComponente::Multipla => Select::make($nome)->multiple()->options($this->opcoes($campo)),
            TipoComponente::Checkboxes => CheckboxList::make($nome)->options($this->opcoes($campo))->columns(2),
            TipoComponente::Tags => TagsInput::make($nome),
            TipoComponente::Toggle => Toggle::make($nome),
            TipoComponente::Checkbox => Checkbox::make($nome),
            TipoComponente::Slider => Slider::make($nome)->range(minValue: $tipo->isUnsigned() ? 0 : -100, maxValue: 100),
            TipoComponente::Data => DatePicker::make($nome)->displayFormat('d/m/Y'),
            TipoComponente::DataHora => DateTimePicker::make($nome)->seconds(false)->displayFormat('d/m/Y H:i'),
            TipoComponente::DataHoraSegundo => DateTimePicker::make($nome)->seconds()->displayFormat('d/m/Y H:i:s'),
            TipoComponente::Hora => TimePicker::make($nome)->seconds(false),
            TipoComponente::HoraSegundo => TimePicker::make($nome)->seconds(),
            TipoComponente::ChaveValor => KeyValue::make($nome),
            TipoComponente::Editor => RichEditor::make($nome),
            TipoComponente::Markdown => MarkdownEditor::make($nome),
            TipoComponente::Codigo => CodeEditor::make($nome),
            TipoComponente::Oculto => Hidden::make($nome),
            TipoComponente::Memo => Textarea::make($nome)->rows(6)->maxLength($tipo->storageType()->hasLength() ? $tamanho : null),
            TipoComponente::Cor => ColorPicker::make($nome),
            TipoComponente::Imagem => FileUpload::make($nome)->image()->disk('public')->directory($pasta),
            TipoComponente::Arquivo => FileUpload::make($nome)->disk('public')->directory($pasta),
            TipoComponente::Senha => TextInput::make($nome)->password()->revealable()->maxLength(255)
                ->formatStateUsing(fn () => null)->dehydrated(fn ($state) => filled($state)),
            TipoComponente::Numero => TextInput::make($nome)->numeric()
                ->when($tipo->isInteger(), fn (TextInput $c) => $c->integer(), fn (TextInput $c) => $c->step('any'))
                ->when($tipo->isUnsigned(), fn (TextInput $c) => $c->minValue(0))
                ->when($tipo === TipoCampo::Year, fn (TextInput $c) => $c->minValue(1901)->maxValue(2155)),
            default => $this->texto($campo, $tamanho),
        };

        $componente->label($campo->nome);

        if (filled($mascara) && $componente instanceof TextInput && $comp->aceitaMascara()) {
            $mascara === '$dinheiro'
                // valor com milhar e decimais: 1.234,56 (grava 1234.56)
                ? $componente->mask(RawJs::make('$money($input, \',\', \'.\', ' . ($campo->escala ?? 2) . ')'))->stripCharacters('.')
                    ->dehydrateStateUsing(fn ($state) => is_string($state) ? str_replace(',', '.', $state) : $state)
                : $componente->mask($mascara);
        }

        if ($obrigatorio ?? $campo->obrigatorio) {
            // senha: obrigatória só ao incluir (na alteração, vazio mantém a atual)
            $tipo === TipoCampo::Password
                ? $componente->required(fn () => $this->view->incluindo())
                : $componente->required();
        }

        if ($campo->ajuda) {
            $componente->helperText($campo->ajuda);
        }

        if (filled($campo->padrao) && ! $tipo->isForeign() && ! $tipo->isUpload() && $tipo !== TipoCampo::Password) {
            $componente->default(SchemaSnapshot::castDefault($tipo, $campo->padrao));
        }

        if ($campo->unico && ! in_array($tipo, [TipoCampo::Boolean, TipoCampo::MultiSelect], true) && ! $tipo->isJson()) {
            $componente->unique(ignoreRecord: true);
        }

        return $componente;
    }

    /**
     * Texto simples (uma linha), com a validação/máscara do tipo do campo.
     */
    protected function texto(MdCampo $campo, int $tamanho): TextInput
    {
        $nome = $campo->nome_fisico;

        return match ($campo->tipo) {
            TipoCampo::Email => TextInput::make($nome)->email()->maxLength($tamanho),
            TipoCampo::Url => TextInput::make($nome)->url()->maxLength($tamanho),
            TipoCampo::Phone => TextInput::make($nome)->tel()->mask('(99) 99999-9999'),
            TipoCampo::Cpf => TextInput::make($nome)->mask('999.999.999-99')->rule(new Cpf),
            TipoCampo::Cnpj => TextInput::make($nome)->mask('99.999.999/9999-99')->rule(new Cnpj),
            TipoCampo::Cep => TextInput::make($nome)->mask('99999-999'),
            TipoCampo::Uuid => TextInput::make($nome)->uuid(),
            TipoCampo::Ulid => TextInput::make($nome)->ulid(),
            TipoCampo::IpAddress => TextInput::make($nome)->ip(),
            TipoCampo::MacAddress => TextInput::make($nome)->macAddress(),
            default => TextInput::make($nome)->maxLength($campo->tipo->storageType()->hasLength() ? $tamanho : null),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function opcoes(MdCampo $campo): array
    {
        return collect($campo->opcoes ?? [])->mapWithKeys(fn ($opcao) => [(string) $opcao => (string) $opcao])->all();
    }

    // ------------------------------------------------------------------
    // Chave estrangeira (F3 / F4)
    // ------------------------------------------------------------------

    protected function fk(MdCampo $campo, ?int $traducaoId): Select
    {
        $destino = $campo->fkTabela;
        $model = $destino ? Registro::model($destino) : null;
        $select = Select::make($campo->nome_fisico)->searchable()->live();

        // na prévia (painel central) a tabela pode ser de clientes: não consulta dados
        if (! $model || $this->previa) {
            return $select;
        }

        // opções pela tradução de lookup escolhida (ou a padrão) da tabela apontada
        $opcoes = fn (?string $search = null) => Traducao::ordenar($model->newQuery(), $destino, Traducao::LOOKUP, $traducaoId)
            ->when(filled($search), fn ($q) => Traducao::buscar($q, $destino, Traducao::LOOKUP, $search, $traducaoId))
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (Model $r) => [$r->getKey() => Traducao::texto($r, $destino, Traducao::LOOKUP, $traducaoId)])
            ->all();

        $select
            ->getSearchResultsUsing(fn (?string $search) => $opcoes($search))
            ->getOptionLabelUsing(fn ($value) => Traducao::texto($model->newQuery()->find($value), $destino, Traducao::LOOKUP, $traducaoId))
            ->options(fn () => $opcoes());

        $formularioDestino = $destino->formularioPadrao();

        // dentro do cadastro rápido não abre outro cadastro rápido
        if (! $formularioDestino || $this->profundidade > 0) {
            return $select;
        }

        $statePath = fn (Select $component) => $component->getStatePath();

        return $select
            ->createOptionForm(fn () => (new static($formularioDestino, Registro::tela($formularioDestino), $this->profundidade + 1))->componentes())
            ->createOptionUsing(function (array $data) use ($destino, $formularioDestino) {
                $registro = Registro::model($destino);
                $view = Registro::tela($formularioDestino);

                return Executor::transacao($registro, function () use ($registro, $view, $data) {
                    $registro->fill($view->antesDeSalvar($data))->save();
                    $view->depoisDeSalvar($registro);

                    return $registro->getKey();
                });
            })
            ->createOptionAction(fn (Action $action) => $action
                ->modalHeading("Incluir {$destino->nome}")
                ->tooltip('Incluir (F3)')
                ->extraAttributes(['data-atalho' => 'f3']))
            ->suffixAction(
                Action::make('visualizar_' . mb_strtolower($campo->nome_fisico))
                    ->icon('heroicon-o-eye')
                    ->tooltip('Visualizar (F4)')
                    ->extraAttributes(['data-atalho' => 'f4'])
                    ->visible(fn (Select $component) => filled($component->getState()))
                    ->modalHeading("Visualizar {$destino->nome}")
                    ->schema(fn () => (new static($formularioDestino, Registro::tela($formularioDestino, BaseResource::VISUALIZAR), $this->profundidade + 1))->componentes())
                    ->fillForm(fn (Select $component) => $model->newQuery()->find($component->getState())?->attributesToArray() ?? [])
                    ->disabledSchema()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
            )
            ->extraFieldWrapperAttributes(fn (Select $component) => [
                'x-on:keydown.f3.prevent.stop' => "if (! \$wire.\$get('{$statePath($component)}')) \$el.querySelector('[data-atalho=f3]')?.click()",
                'x-on:keydown.f4.prevent.stop' => "if (\$wire.\$get('{$statePath($component)}')) \$el.querySelector('[data-atalho=f4]')?.click()",
            ]);
    }
}
