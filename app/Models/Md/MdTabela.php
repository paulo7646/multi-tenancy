<?php

namespace App\Models\Md;

use App\Sistema\Enums\Banco;
use App\Sistema\Enums\Cor;
use App\Sistema\Enums\TipoTabela;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tabela do dicionário de dados. Os arquivos gerados ficam em
 * app/Modulos/{pasta do módulo}/{NOME_FISICO}/. Nomes físicos em maiúscula.
 */
class MdTabela extends Model
{
    /** Coluna da situação (quando a tabela controla situação): FK para md_status */
    public const COLUNA_SITUACAO = 'MD_STATUS_ID';

    protected $connection = 'central';

    protected $table = 'md_tabelas';

    protected $fillable = [
        'modulo_id', 'nome', 'nome_fisico', 'nome_controller', 'descricao', 'banco', 'tipo', 'icone', 'cor',
        'controla_situacao', 'exibe_filho', 'campo_exibicao', 'generated_schema', 'itens_gerados', 'generated_at',
        'existente', 'classe_model',
    ];

    protected function casts(): array
    {
        return [
            'banco' => Banco::class,
            'tipo' => TipoTabela::class,
            'controla_situacao' => 'boolean',
            'existente' => 'boolean',
            'generated_schema' => 'array',
            'itens_gerados' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // mudar a tabela de módulo: as referências que ela faz e as que recebe continuam valendo?
        static::saving(function (MdTabela $tabela) {
            if (! $tabela->exists || ! $tabela->isDirty('modulo_id')) {
                return;
            }

            $novo = $tabela->modulo_id;
            $padroes = Modulo::query()->where('padrao', true)->select('id');
            $novoEPadrao = Modulo::query()->whereKey($novo)->where('padrao', true)->exists();

            // as que ela faz: só para o novo módulo ou para um padrão
            $saindo = MdCampo::query()->where('md_tabela_id', $tabela->id)->whereNotNull('fk_tabela_id')
                ->whereHas('fkTabela', fn ($q) => $q->where('modulo_id', '!=', $novo)->whereNotIn('modulo_id', $padroes))
                ->count();

            // as que ela recebe: só de tabelas do novo módulo, ou de qualquer módulo se ela virar padrão
            $entrando = $novoEPadrao ? 0 : MdCampo::query()->where('fk_tabela_id', $tabela->id)
                ->whereHas('tabela', fn ($q) => $q->where('modulo_id', '!=', $novo))
                ->count();

            if ($saindo + $entrando > 0) {
                throw ValidationException::withMessages([
                    'modulo_id' => "A tabela {$tabela->nome_fisico} tem referências (FK) que não valem no outro módulo. Desfaça-as antes de mudar de módulo.",
                ]);
            }
        });
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Tabelas que podem ser referenciadas por uma tabela do módulo dado: as do próprio módulo e as dos
     * módulos padrão (Estrutura, que todo cliente tem). Qualquer outro módulo pode não existir no cliente.
     */
    public function scopePermitidasPara(Builder $query, ?int $moduloId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('modulo_id', $moduloId)
            ->orWhereIn('modulo_id', Modulo::query()->where('padrao', true)->select('id')));
    }

    /**
     * Esta tabela pode referenciar a outra (FK, tab page de tabela...)?
     */
    public function podeReferenciar(MdTabela $alvo): bool
    {
        return $alvo->modulo_id === $this->modulo_id
            || (bool) Modulo::query()->whereKey($alvo->modulo_id)->where('padrao', true)->exists();
    }

    public function campos(): HasMany
    {
        return $this->hasMany(MdCampo::class)->orderBy('ordem')->orderBy('id');
    }

    public function situacoes(): HasMany
    {
        return $this->hasMany(MdTabelaStatus::class)->orderBy('ordem')->orderBy('id');
    }

    public function traducoes(): HasMany
    {
        return $this->hasMany(MdTabelaTraducao::class)->orderBy('ordem')->orderBy('id');
    }

    public function traducoesTabela(): HasMany
    {
        return $this->traducoes()->where('tipo', MdTabelaTraducao::TABELA);
    }

    public function traducoesLookup(): HasMany
    {
        return $this->traducoes()->where('tipo', MdTabelaTraducao::LOOKUP);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(MdTabelaItem::class)->orderBy('ordem')->orderBy('id');
    }

    public function formularios(): HasMany
    {
        // ordem fixa: o pacote de release e o JSON do dicionário não podem mudar só porque o PostgreSQL reordenou
        return $this->hasMany(MdFormulario::class)->orderBy('id');
    }

    public function gerenciamentos(): HasMany
    {
        // ordem fixa: sem ORDER BY o PostgreSQL muda a ordem depois de um UPDATE e o pacote/JSON mudariam à toa
        return $this->hasMany(MdGerenciamento::class)->orderBy('id');
    }

    /**
     * Campos de outras tabelas que apontam (FK) para esta.
     */
    public function referenciadaPor(): HasMany
    {
        return $this->hasMany(MdCampo::class, 'fk_tabela_id');
    }

    public function isGerada(): bool
    {
        return $this->generated_at !== null;
    }

    /**
     * Tabela de ligação que mostra o filho: na tab page abre o formulário do outro
     * lado da ligação e a tabela não tem formulário nem tela própria.
     */
    public function exibeFilho(): bool
    {
        return $this->tipoTabela() === TipoTabela::Ligacao && (bool) ($this->exibe_filho ?? true);
    }

    /**
     * Tem formulário e gerenciamento próprios? Ligação que exibe o filho, não.
     */
    public function temTelas(): bool
    {
        return $this->tipoTabela()->temTelas() || ($this->tipoTabela() === TipoTabela::Ligacao && ! $this->exibeFilho());
    }

    public function tipoTabela(): TipoTabela
    {
        return $this->tipo ?? TipoTabela::Cadastro;
    }

    // ------------------------------------------------------------------
    // Arquivos e classes gerados
    // ------------------------------------------------------------------

    public function namespace(): string
    {
        return $this->modulo->namespace().'\\'.$this->nome_fisico;
    }

    public function caminho(string $arquivo = ''): string
    {
        return $this->modulo->caminho(rtrim("{$this->nome_fisico}/{$arquivo}", '/'));
    }

    /**
     * Prefixo do nome físico das tabelas do módulo: OP_.
     */
    public static function prefixoFisico(?string $prefixo): string
    {
        return mb_strtoupper((string) $prefixo).'_';
    }

    /**
     * Nome físico sem o prefixo do módulo: OP_PRODUTO -> PRODUTO.
     */
    public function nomeSemPrefixo(): string
    {
        $prefixo = static::prefixoFisico($this->modulo?->prefixo);

        return $this->modulo && str_starts_with(mb_strtoupper($this->nome_fisico), $prefixo)
            ? substr($this->nome_fisico, strlen($prefixo))
            : $this->nome_fisico;
    }

    /**
     * Base dos nomes de classe: OP_PRODUTO -> Produto, OP_STATUS_PEDIDO -> StatusPedido.
     */
    public function nomeClasse(): string
    {
        return Str::studly(mb_strtolower($this->nomeSemPrefixo()));
    }

    public function classeModel(): string
    {
        return 'Model'.$this->nomeClasse();
    }

    /**
     * Controller de regras de negócio (nome_controller; padrão: ControllerProduto).
     */
    public function classeController(): string
    {
        return $this->nome_controller ?: 'Controller'.$this->nomeClasse();
    }

    public function controllerFqcn(): string
    {
        return $this->classeFqcn($this->classeController());
    }

    public function modelFqcn(): string
    {
        // tabela feita à mão e incorporada ao dicionário: o model é o que já existe, não o gerado
        return $this->classe_model ?: $this->namespace().'\\'.$this->classeModel();
    }

    /**
     * Enum das tabelas lista/conteúdo: App\Modulos\{Modulo}\Enums\{NomeClasse}.
     */
    public function enumFqcn(): string
    {
        return $this->modulo->namespace().'\\Enums\\'.$this->nomeClasse();
    }

    public function enumPath(): string
    {
        return $this->modulo->caminho('Enums/'.$this->nomeClasse().'.php');
    }

    /**
     * Classe sugerida para os botões (todos os botões da tabela, se não customizar).
     */
    public function classeBotoesPadrao(): string
    {
        return 'Botoes'.$this->nomeClasse();
    }

    public function classeFqcn(string $classe): string
    {
        return $this->namespace().'\\'.$classe;
    }

    /**
     * Formulário usado no cadastro rápido (F3) e na visualização (F4) quando outra tabela aponta para esta.
     */
    public function formularioPadrao(): ?MdFormulario
    {
        return $this->gerenciamentos()->whereNotNull('md_formulario_id')->first()?->formulario
            ?? $this->formularios()->orderBy('id')->first();
    }

    /**
     * Situação padrão (preenchida ao incluir registros).
     */
    public function situacaoPadrao(): ?int
    {
        if (! $this->controla_situacao) {
            return null;
        }

        $situacoes = $this->situacoes()->with('status')->get();

        return ($situacoes->firstWhere('padrao', true) ?? $situacoes->first())?->status?->id;
    }

    /**
     * Ícone do menu: com cor, vira o desenho já colorido.
     */
    public function iconeMenu(string $padrao = 'heroicon-o-table-cells'): string|HtmlString
    {
        $icone = $this->icone ?: $padrao;

        return static::iconeColorido($icone, $this->cor);
    }

    public static function iconeColorido(string $icone, ?string $cor): string|HtmlString
    {
        if (blank($cor)) {
            return $icone;
        }

        return new HtmlString(svg($icone, '', ['style' => 'width: 100%; height: 100%; color: '.Cor::css($cor)])->toHtml());
    }
}
