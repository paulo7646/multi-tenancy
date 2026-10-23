<?php

namespace App\Sistema\Runtime;

use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;
use App\Sistema\Registro;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filtros do datagrid a partir do gerenciamento (md_gerenciamentos.filtros).
 * O tipo do campo define o filtro:
 *  - chave estrangeira: combo com até 100 registros, pesquisável pela tradução de lookup;
 *  - data / data e hora: "de" e "até" (sem "de": desde 01/01/1900; sem "até": até hoje);
 *  - número: "de" e "até";
 *  - sim/não, lista de opções e situação: combo;
 *  - texto: contém.
 */
class FiltrosBuilder
{
    public const LIMITE_FK = 100;

    public function __construct(protected MdGerenciamento $gerenciamento) {}

    /**
     * @return list<Filter>
     */
    public function filtros(): array
    {
        $tabela = $this->gerenciamento->tabela;
        $campos = $tabela->campos->keyBy('uid');

        return collect($this->gerenciamento->filtros ?? [])
            ->values()
            ->map(function (array $filtro, int $i) use ($campos, $tabela) {
                $nome = $filtro['nome'] ?? null;
                $uid = $filtro['campo'] ?? null;

                if ($uid === MdFormulario::CAMPO_SITUACAO) {
                    return $tabela->controla_situacao ? $this->situacao("filtro_{$i}", $nome ?: 'Situação') : null;
                }

                $campo = $campos->get($uid);

                return $campo ? $this->filtro("filtro_{$i}", $nome ?: $campo->nome, $campo) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * O gerenciamento já tem filtro de situação configurado?
     */
    public function temFiltroDeSituacao(): bool
    {
        return collect($this->gerenciamento->filtros ?? [])->contains(fn ($f) => ($f['campo'] ?? null) === MdFormulario::CAMPO_SITUACAO);
    }

    protected function filtro(string $chave, string $nome, MdCampo $campo): ?Filter
    {
        $tipo = $campo->tipo;
        $coluna = $campo->nome_fisico;

        return match (true) {
            $tipo->isForeign() && $campo->fkTabela => $this->fk($chave, $nome, $campo),
            $tipo->isDate() || $tipo->isDateTime() => $this->periodo($chave, $nome, $coluna, $tipo->isDateTime()),
            $tipo === TipoCampo::Boolean => $this->combo($chave, $nome, $coluna, ['1' => 'Sim', '0' => 'Não'], fn ($v) => (bool) $v),
            $tipo === TipoCampo::Enum => $this->combo($chave, $nome, $coluna, collect($campo->opcoes ?? [])->mapWithKeys(fn ($o) => [(string) $o => (string) $o])->all(), multiplo: true),
            $tipo === TipoCampo::MultiSelect => $this->multipla($chave, $nome, $coluna, $campo->opcoes ?? []),
            $tipo->isNumeric() => $this->faixa($chave, $nome, $coluna),
            $tipo->isJson(), $tipo->isUpload(), $tipo === TipoCampo::Password => null,
            default => $this->contem($chave, $nome, $coluna),
        };
    }

    protected function fk(string $chave, string $nome, MdCampo $campo): Filter
    {
        $destino = $campo->fkTabela;
        $model = Registro::model($destino);

        $opcoes = fn (?string $search = null) => Traducao::ordenar($model->newQuery(), $destino, Traducao::LOOKUP)
            ->when(filled($search), fn ($q) => Traducao::buscar($q, $destino, Traducao::LOOKUP, $search))
            ->limit(static::LIMITE_FK)
            ->get()
            ->mapWithKeys(fn (Model $r) => [$r->getKey() => Traducao::texto($r, $destino, Traducao::LOOKUP)])
            ->all();

        return Filter::make($chave)
            ->schema([
                Select::make('valor')
                    ->label($nome)
                    ->searchable()
                    ->options(fn () => $opcoes())
                    ->getSearchResultsUsing(fn (?string $search) => $opcoes($search))
                    ->getOptionLabelUsing(fn ($value) => Traducao::texto($model->newQuery()->find($value), $destino, Traducao::LOOKUP)),
            ])
            ->query(fn (Builder $query, array $data) => $query->when(filled($data['valor'] ?? null), fn ($q) => $q->where($campo->nome_fisico, $data['valor'])))
            ->indicateUsing(fn (array $data) => filled($data['valor'] ?? null)
                ? "{$nome}: " . Traducao::texto($model->newQuery()->find($data['valor']), $destino, Traducao::LOOKUP)
                : null);
    }

    /**
     * De / até. Preenchendo só um, o outro vira 01/01/1900 ou hoje.
     */
    protected function periodo(string $chave, string $nome, string $coluna, bool $comHora): Filter
    {
        return Filter::make($chave)
            ->schema([
                DatePicker::make('de')->label("{$nome} de"),
                DatePicker::make('ate')->label("{$nome} até"),
            ])
            ->columns(2)
            ->query(function (Builder $query, array $data) use ($coluna, $comHora) {
                if (blank($data['de'] ?? null) && blank($data['ate'] ?? null)) {
                    return $query;
                }

                [$de, $ate] = static::limitesDoPeriodo($data['de'] ?? null, $data['ate'] ?? null);

                return $comHora
                    ? $query->whereBetween($coluna, [$de->startOfDay(), $ate->endOfDay()])
                    : $query->whereBetween($coluna, [$de->toDateString(), $ate->toDateString()]);
            })
            ->indicateUsing(function (array $data) use ($nome) {
                if (blank($data['de'] ?? null) && blank($data['ate'] ?? null)) {
                    return [];
                }

                [$de, $ate] = static::limitesDoPeriodo($data['de'] ?? null, $data['ate'] ?? null);

                return [Indicator::make("{$nome}: {$de->format('d/m/Y')} até {$ate->format('d/m/Y')}")];
            });
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function limitesDoPeriodo(?string $de, ?string $ate): array
    {
        return [
            filled($de) ? Carbon::parse($de) : Carbon::create(1900, 1, 1),
            filled($ate) ? Carbon::parse($ate) : Carbon::today(),
        ];
    }

    protected function faixa(string $chave, string $nome, string $coluna): Filter
    {
        return Filter::make($chave)
            ->schema([
                TextInput::make('de')->label("{$nome} de")->numeric(),
                TextInput::make('ate')->label("{$nome} até")->numeric(),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data) => $query
                ->when(filled($data['de'] ?? null), fn ($q) => $q->where($coluna, '>=', $data['de']))
                ->when(filled($data['ate'] ?? null), fn ($q) => $q->where($coluna, '<=', $data['ate'])))
            ->indicateUsing(fn (array $data) => filled($data['de'] ?? null) || filled($data['ate'] ?? null)
                ? "{$nome}: " . ($data['de'] ?? '...') . ' até ' . ($data['ate'] ?? '...')
                : null);
    }

    protected function combo(string $chave, string $nome, string $coluna, array $opcoes, ?\Closure $converter = null, bool $multiplo = false): Filter
    {
        return Filter::make($chave)
            ->schema([
                Select::make('valor')->label($nome)->options($opcoes)->multiple($multiplo),
            ])
            ->query(function (Builder $query, array $data) use ($coluna, $converter, $multiplo) {
                $valor = $data['valor'] ?? null;

                if (blank($valor)) {
                    return $query;
                }

                return $multiplo ? $query->whereIn($coluna, (array) $valor) : $query->where($coluna, $converter ? $converter($valor) : $valor);
            })
            ->indicateUsing(fn (array $data) => filled($data['valor'] ?? null)
                ? "{$nome}: " . collect((array) $data['valor'])->map(fn ($v) => $opcoes[$v] ?? $v)->join(', ')
                : null);
    }

    protected function multipla(string $chave, string $nome, string $coluna, array $opcoes): Filter
    {
        $opcoes = collect($opcoes)->mapWithKeys(fn ($o) => [(string) $o => (string) $o])->all();

        return Filter::make($chave)
            ->schema([
                Select::make('valor')->label($nome)->options($opcoes)->multiple(),
            ])
            // registros que têm alguma das opções marcadas
            ->query(fn (Builder $query, array $data) => $query->when(filled($data['valor'] ?? null), fn ($q) => $q->where(function ($w) use ($coluna, $data) {
                foreach ((array) $data['valor'] as $opcao) {
                    $w->orWhereJsonContains($coluna, $opcao);
                }
            })))
            ->indicateUsing(fn (array $data) => filled($data['valor'] ?? null) ? "{$nome}: " . implode(', ', (array) $data['valor']) : null);
    }

    protected function contem(string $chave, string $nome, string $coluna): Filter
    {
        return Filter::make($chave)
            ->schema([
                TextInput::make('valor')->label("{$nome} contém"),
            ])
            ->query(fn (Builder $query, array $data) => $query->when(filled($data['valor'] ?? null), fn ($q) => $q->where($coluna, 'ilike', '%' . $data['valor'] . '%')))
            ->indicateUsing(fn (array $data) => filled($data['valor'] ?? null) ? "{$nome} contém \"{$data['valor']}\"" : null);
    }

    protected function situacao(string $chave, string $nome): Filter
    {
        $tabela = $this->gerenciamento->tabela;

        return $this->combo(
            $chave,
            $nome,
            MdTabela::COLUNA_SITUACAO,
            $tabela->situacoes->filter(fn ($s) => $s->status)->mapWithKeys(fn ($s) => [$s->status->id => $s->status->nome])->all(),
            multiplo: true,
        );
    }
}
