<?php

namespace App\Sistema\Runtime;

use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Models\Md\MdStatus;
use App\Sistema\Base\BaseResource;
use App\Sistema\Enums\TipoCampo;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Colunas do datagrid a partir do gerenciamento (md_gerenciamentos.colunas).
 * Sem colunas configuradas: todos os campos da tabela, na ordem do cadastro.
 */
class ColunasBuilder
{
    public function __construct(
        protected MdGerenciamento $gerenciamento,
        protected BaseResource $classe,
    ) {}

    /**
     * @return list<Column>
     */
    public function colunas(): array
    {
        $tabela = $this->gerenciamento->tabela;
        $campos = $tabela->campos->keyBy('uid');

        $config = collect($this->gerenciamento->colunas ?: static::padrao($tabela->campos, $tabela->controla_situacao));

        return $config
            ->map(function (array $item) use ($campos, $tabela) {
                if (($item['campo'] ?? null) === MdFormulario::CAMPO_SITUACAO) {
                    return $tabela->controla_situacao ? [$this->situacao($tabela->situacoes->pluck('status')->filter()), MdTabela::COLUNA_SITUACAO, $item] : null;
                }

                $campo = $campos->get($item['campo'] ?? '');

                return $campo && $campo->tipo !== TipoCampo::Password ? [$this->coluna($campo, isset($item['traducao']) && is_numeric($item['traducao']) ? (int) $item['traducao'] : null), $campo->nome_fisico, $item, $campo] : null;
            })
            ->filter()
            ->map(function (array $par) {
                [$coluna, $nomeFisico, $item] = $par;
                $campo = $par[3] ?? null;

                if (! empty($item['pesquisavel'])) {
                    // FK: pesquisa nos campos da tradução da tabela apontada
                    $campo?->isFk() && $campo->fkTabela
                        ? $coluna->searchable(query: fn (Builder $query, string $search) => $query->whereHas($campo->nomeRelacao(), fn (Builder $r) => Traducao::buscar($r, $campo->fkTabela, Traducao::TABELA, $search, isset($item['traducao']) && is_numeric($item['traducao']) ? (int) $item['traducao'] : null)))
                        : $coluna->searchable();
                }

                if (! empty($item['ordenavel'])) {
                    $coluna->sortable();
                }

                $coluna->toggleable(isToggledHiddenByDefault: ! empty($item['oculta']));

                return $this->classe->personalizarColuna($nomeFisico, $coluna);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MdCampo>  $campos
     */
    public static function padrao(Collection $campos, bool $controlaSituacao): array
    {
        $colunas = $campos
            ->reject(fn (MdCampo $c) => $c->tipo === TipoCampo::Password || $c->tipo->isJson())
            ->map(fn (MdCampo $c) => ['campo' => $c->uid, 'pesquisavel' => $c->tipo->storageType() === TipoCampo::String, 'ordenavel' => true, 'oculta' => false])
            ->values()
            ->all();

        if ($controlaSituacao) {
            $colunas[] = ['campo' => MdFormulario::CAMPO_SITUACAO, 'pesquisavel' => false, 'ordenavel' => true, 'oculta' => false];
        }

        return $colunas;
    }

    protected function coluna(MdCampo $campo, ?int $traducaoId = null): Column
    {
        $tipo = $campo->tipo;
        $nome = $campo->nome_fisico;

        $coluna = match (true) {
            // FK: a tradução de tabela do registro apontado (codigo - nome)
            $tipo->isForeign() && $campo->fkTabela => TextColumn::make($nome)
                ->getStateUsing(fn (Model $record) => Traducao::texto($record->{$campo->nomeRelacao()}, $campo->fkTabela, Traducao::TABELA, $traducaoId)),
            $tipo === TipoCampo::Boolean => IconColumn::make($nome)->boolean(),
            $tipo === TipoCampo::Color => ColorColumn::make($nome),
            $tipo === TipoCampo::Image => ImageColumn::make($nome)->disk('public'),
            $tipo === TipoCampo::MultiSelect, $tipo === TipoCampo::Enum => TextColumn::make($nome)->badge(),
            $tipo->isDate() => TextColumn::make($nome)->date('d/m/Y'),
            $tipo->isDateTime() => TextColumn::make($nome)->dateTime('d/m/Y H:i'),
            $tipo === TipoCampo::Decimal => TextColumn::make($nome)->numeric(decimalPlaces: $campo->escala ?? 2, decimalSeparator: ',', thousandsSeparator: '.'),
            in_array($tipo, [TipoCampo::Text, TipoCampo::TinyText, TipoCampo::MediumText, TipoCampo::LongText], true) => TextColumn::make($nome)->limit(60)->html($tipo === TipoCampo::LongText),
            default => TextColumn::make($nome)->limit(60),
        };

        return $coluna->label($campo->nome);
    }

    /**
     * @param  Collection<int, MdStatus>  $status
     */
    protected function situacao(Collection $status): Column
    {
        $porCodigo = $status->keyBy('id');

        return TextColumn::make(MdTabela::COLUNA_SITUACAO)
            ->label('Situação')
            ->badge()
            ->formatStateUsing(fn ($state) => $porCodigo->get($state)?->nome ?? $state)
            ->icon(fn ($state) => $porCodigo->get($state)?->icone)
            ->color(fn ($state) => $porCodigo->get($state)?->corFilament() ?? 'gray');
    }
}
