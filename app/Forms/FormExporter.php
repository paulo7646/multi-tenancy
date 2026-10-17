<?php

namespace App\Forms;

use App\Forms\Enums\FormConnection;
use App\Forms\Generators\Files;
use App\Models\FormButton;
use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormFilter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Definições dos formulários em JSON (database/forms), versionadas no git.
 * São a fonte para remontar o cadastro em outra máquina (php artisan forms:import).
 */
class FormExporter
{
    public static function directory(): string
    {
        return database_path('forms');
    }

    public static function path(FormDefinition $form): string
    {
        return static::directory() . "/{$form->database->value}_{$form->table_name}.json";
    }

    public static function export(FormDefinition $form): void
    {
        $form->load(['fields.lookupForm', 'buttons', 'filters']);

        $data = [
            ...$form->only(['name', 'title', 'table_name', 'model_class', 'controller_class', 'resource_class',
                'navigation_group', 'navigation_icon', 'lookup_field', 'roles', 'generated_schema']),
            'database' => $form->database->value,
            'generated_at' => $form->generated_at?->toIso8601String(),
            'fields' => $form->fields->map(fn (FormField $field) => [
                ...collect($field->only($field->getFillable()))->except(['form_definition_id', 'lookup_form_id'])->all(),
                'type' => $field->type->value,
                // lookup pela tabela (os ids mudam de uma máquina para outra)
                'lookup' => $field->lookupForm ? ['database' => $field->lookupForm->database->value, 'table' => $field->lookupForm->table_name] : null,
            ])->values()->all(),
            'buttons' => $form->buttons->map(fn (FormButton $button) => [
                ...collect($button->only($button->getFillable()))->except('form_definition_id')->all(),
                'color' => $button->color->value,
                'position' => $button->position->value,
                'action' => $button->action->value,
            ])->values()->all(),
            'filters' => $form->filters->map(fn (FormFilter $filter) => [
                ...collect($filter->only($filter->getFillable()))->except('form_definition_id')->all(),
                'type' => $filter->type->value,
            ])->values()->all(),
        ];

        Files::put(static::path($form), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }

    public static function delete(FormDefinition $form): void
    {
        Files::delete(static::path($form));
    }

    /**
     * Recria/atualiza o cadastro a partir dos JSON.
     *
     * @return list<string> formulários importados
     */
    public static function import(): array
    {
        $files = glob(static::directory() . '/*.json') ?: [];
        $items = array_map(fn (string $file) => json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR), $files);

        return DB::connection('central')->transaction(function () use ($items) {
            $forms = [];

            // 1) formulários (os lookups precisam de todos criados)
            foreach ($items as $item) {
                $forms[] = [$item, FormDefinition::updateOrCreate(
                    ['database' => $item['database'], 'table_name' => $item['table_name']],
                    collect($item)->except(['fields', 'buttons', 'filters', 'database', 'table_name'])->all(),
                )];
            }

            // 2) campos (pelo uid), botões e filtros
            foreach ($forms as [$item, $form]) {
                $uids = [];

                foreach ($item['fields'] as $sort => $field) {
                    $lookup = $field['lookup'] ? FormDefinition::where('database', $field['lookup']['database'])
                        ->where('table_name', $field['lookup']['table'])->first() : null;

                    if ($field['lookup'] && ! $lookup) {
                        throw new RuntimeException("{$form->title}.{$field['name']}: lookup para {$field['lookup']['table']} não encontrado.");
                    }

                    FormField::updateOrCreate(['uid' => $field['uid']], [
                        ...collect($field)->except(['lookup', 'uid'])->all(),
                        'form_definition_id' => $form->id,
                        'lookup_form_id' => $lookup?->id,
                    ]);

                    $uids[] = $field['uid'];
                }

                $form->fields()->whereNotIn('uid', $uids)->delete();

                $form->buttons()->delete();
                foreach ($item['buttons'] as $button) {
                    $form->buttons()->create($button);
                }

                $form->filters()->delete();
                foreach ($item['filters'] as $filter) {
                    $form->filters()->create($filter);
                }
            }

            return array_map(fn (array $pair) => FormConnection::from($pair[1]->database->value)->getLabel() . ": {$pair[1]->title}", $forms);
        });
    }
}
