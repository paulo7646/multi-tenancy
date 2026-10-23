<?php

namespace App\Sistema\Schema;

use App\Models\Md\MdCampo;
use App\Models\Md\MdStatus;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoCampo;
use Illuminate\Support\Collection;

/**
 * "Foto" da estrutura que a tabela física deve ter: ['fields' => [uid => coluna]].
 *
 * As colunas são indexadas pelo uid do campo (fixo): trocar o nome físico é
 * reconhecido como rename. A situação (quando a tabela controla situação)
 * entra como a coluna "situacao", restrita aos códigos habilitados.
 */
class SchemaSnapshot
{
    public const UID_SITUACAO = 'situacao';

    public static function of(MdTabela $tabela): array
    {
        $tabela->loadMissing(['campos.fkTabela', 'situacoes.status']);

        return static::build(
            $tabela->campos,
            $tabela->controla_situacao,
            $tabela->situacoes->map(fn ($s) => $s->status?->nome_fisico)->filter()->values()->all(),
            $tabela->situacaoPadrao(),
        );
    }

    /**
     * A partir do que está na tela, antes de salvar.
     *
     * @param  list<array<string, mixed>>  $campos  estado do repeater de campos
     * @param  list<array<string, mixed>>  $situacoes  estado do repeater de situações
     */
    public static function fromState(array $campos, bool $controlaSituacao, array $situacoes): array
    {
        $fks = MdTabela::whereIn('id', collect($campos)->pluck('fk_tabela_id')->filter())->get()->keyBy('id');
        $status = MdStatus::whereIn('id', collect($situacoes)->pluck('md_status_id')->filter())->get()->keyBy('id');

        $models = collect($campos)->map(function (array $state) use ($fks) {
            $campo = new MdCampo(collect($state)->only((new MdCampo)->getFillable())->all());
            $campo->uid = $state['uid'];
            $campo->setRelation('fkTabela', $fks->get($state['fk_tabela_id'] ?? null));

            return $campo;
        });

        $codigos = collect($situacoes)->map(fn ($s) => $status->get($s['md_status_id'] ?? null)?->nome_fisico)->filter()->values();
        $padrao = collect($situacoes)->first(fn ($s) => ! empty($s['padrao']));

        return static::build(
            $models,
            $controlaSituacao,
            $codigos->all(),
            $padrao ? $status->get($padrao['md_status_id'] ?? null)?->nome_fisico : $codigos->first(),
        );
    }

    public static function empty(): array
    {
        return ['fields' => []];
    }

    /**
     * @param  Collection<int, MdCampo>  $campos
     */
    protected static function build(Collection $campos, bool $controlaSituacao, array $codigosSituacao, ?string $padrao): array
    {
        $fields = $campos->mapWithKeys(fn (MdCampo $campo) => [$campo->uid => static::field($campo)])->all();

        if ($controlaSituacao) {
            $fields[static::UID_SITUACAO] = [
                'name' => MdTabela::COLUNA_SITUACAO,
                'type' => TipoCampo::Enum->value,
                'length' => null,
                'precision' => null,
                'scale' => null,
                'options' => array_values($codigosSituacao),
                'nullable' => true,
                'default' => $padrao,
                'unique' => false,
                'index' => true,
                'foreign' => null,
            ];
        }

        return ['fields' => $fields];
    }

    /**
     * @return array<string, mixed>
     */
    public static function field(MdCampo $campo): array
    {
        $type = $campo->tipo->storageType();

        return [
            'name' => $campo->nome_fisico,
            'type' => $type->value,
            'length' => $type->hasLength() ? ((int) $campo->tamanho ?: $campo->tipo->defaultLength()) : null,
            'precision' => $type->hasPrecision() ? ((int) $campo->precisao ?: ($type === TipoCampo::Decimal ? 10 : 53)) : null,
            'scale' => $type->hasScale() ? (int) ($campo->escala ?? 2) : null,
            // só o enum vira regra no banco; a múltipla escolha é validada na tela
            'options' => $type === TipoCampo::Enum ? array_values(array_map('strval', $campo->opcoes ?? [])) : null,
            'nullable' => ! $campo->obrigatorio,
            'default' => filled($campo->padrao) ? (string) $campo->padrao : null,
            'unique' => (bool) $campo->unico,
            'index' => (bool) $campo->indice,
            'foreign' => $campo->isFk() && $campo->fkTabela
                ? ['table' => $campo->fkTabela->nome_fisico, 'on_delete' => $campo->fk_on_delete ?: 'restrict']
                : null,
        ];
    }

    /**
     * Converte o valor padrão (texto do cadastro) para o tipo da coluna.
     */
    public static function castDefault(TipoCampo $type, string $value): string|int|float|bool
    {
        $type = $type->storageType();

        return match (true) {
            $type === TipoCampo::Boolean => in_array(mb_strtolower($value), ['1', 'true', 'sim', 'yes', 's'], true),
            $type->isInteger(), $type === TipoCampo::Year => (int) $value,
            $type->isNumeric() => (float) $value,
            default => $value,
        };
    }

    /**
     * Nome de índice/constraint fixo pelo uid do campo (sobrevive a rename).
     */
    public static function constraintName(string $table, string $uid, string $suffix): string
    {
        return "{$table}_" . substr(str_replace('-', '', $uid), 0, 8) . "_{$suffix}";
    }
}
