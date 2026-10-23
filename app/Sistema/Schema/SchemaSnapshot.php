<?php

namespace App\Forms\Schema;

use App\Forms\Enums\FieldType;
use App\Models\FormDefinition;
use App\Models\FormField;

/**
 * "Foto" da estrutura que a tabela do formulário deve ter:
 * ['fields' => [uid => campo], 'roles' => [...]].
 *
 * Os campos são indexados pelo uid (fixo, vai para o git): renomear um campo
 * é reconhecido como rename, e reimportar em outra máquina não muda nada.
 */
class SchemaSnapshot
{
    /**
     * Snapshot dos campos salvos no banco.
     */
    public static function of(FormDefinition $form): array
    {
        return [
            'fields' => $form->fields()->with('lookupForm')->get()
                ->mapWithKeys(fn (FormField $field) => [$field->uid => static::field($field)])
                ->all(),
            'roles' => static::roles($form->roles),
        ];
    }

    /**
     * Snapshot a partir do que está na tela (antes de salvar).
     */
    public static function fromState(array $fieldsState, ?array $roles): array
    {
        $lookups = FormDefinition::whereIn('id', collect($fieldsState)->pluck('lookup_form_id')->filter())->get()->keyBy('id');

        return [
            'fields' => collect($fieldsState)
                ->mapWithKeys(function (array $state) use ($lookups) {
                    $field = new FormField(collect($state)->only((new FormField)->getFillable())->all());
                    $field->setRelation('lookupForm', $lookups->get($state['lookup_form_id'] ?? null));

                    return [$state['uid'] => static::field($field)];
                })
                ->all(),
            'roles' => static::roles($roles),
        ];
    }

    public static function empty(): array
    {
        return ['fields' => [], 'roles' => []];
    }

    /**
     * @return array<string, mixed>
     */
    public static function field(FormField $field): array
    {
        $type = $field->type->storageType();

        return [
            'name' => $field->name,
            'type' => $type->value,
            'length' => $type->hasLength() ? ((int) $field->length ?: $field->type->defaultLength()) : null,
            'precision' => $type->hasPrecision() ? ((int) $field->precision ?: ($type === FieldType::Decimal ? 10 : 53)) : null,
            'scale' => $type->hasScale() ? (int) ($field->scale ?? 2) : null,
            // só o enum vira regra no banco; a múltipla escolha é validada na tela
            'options' => $type === FieldType::Enum ? array_values(array_map('strval', $field->options ?? [])) : null,
            'nullable' => (bool) $field->nullable,
            'default' => filled($field->default) ? (string) $field->default : null,
            'unique' => (bool) $field->unique,
            'index' => (bool) $field->index,
            'foreign' => $field->type->isForeign() && $field->lookupForm
                ? ['table' => $field->lookupForm->table_name, 'on_delete' => $field->on_delete ?: 'restrict']
                : null,
        ];
    }

    /**
     * Converte o valor padrão (texto do cadastro) para o tipo da coluna.
     */
    public static function castDefault(FieldType $type, string $value): string|int|float|bool
    {
        $type = $type->storageType();

        return match (true) {
            $type === FieldType::Boolean => in_array(mb_strtolower($value), ['1', 'true', 'sim', 'yes', 's'], true),
            $type->isInteger(), $type === FieldType::Year => (int) $value,
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

    protected static function roles(?array $roles): array
    {
        $roles = array_values(array_unique(array_filter($roles ?? [])));
        sort($roles);

        return $roles;
    }
}
