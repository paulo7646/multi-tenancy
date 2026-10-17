<?php

namespace App\Forms\Generators;

use App\Forms\Enums\FieldType;
use App\Forms\Enums\FormConnection;
use App\Models\FormDefinition;
use App\Models\FormField;

/**
 * Gera o model em duas camadas:
 *  - app/Forms/Generated/Models/{Model}Base.php  recriado a cada geração;
 *  - app/Models/Forms/{Model}.php                criado uma vez, para você personalizar.
 */
class ModelWriter
{
    public static function basePath(FormDefinition $form): string
    {
        return app_path("Forms/Generated/Models/{$form->model_class}Base.php");
    }

    public static function path(FormDefinition $form): string
    {
        return app_path("Models/Forms/{$form->model_class}.php");
    }

    public static function write(FormDefinition $form): void
    {
        $fields = $form->fields()->with('lookupForm')->get();
        $export = fn (array $values) => implode("\n", array_map(fn ($value) => "        '{$value}',", $values));

        $fillable = $export($fields->pluck('name')->all());
        $hidden = $export($fields->filter(fn (FormField $field) => $field->type === FieldType::Password)->pluck('name')->all());

        $casts = $fields
            ->filter(fn (FormField $field) => $field->type->cast($field->scale) !== null)
            ->map(fn (FormField $field) => "            '{$field->name}' => '{$field->type->cast($field->scale)}',")
            ->implode("\n");

        $relations = $fields
            ->filter(fn (FormField $field) => $field->type->isForeign() && $field->lookupForm)
            ->map(fn (FormField $field) => <<<PHP

    public function {$field->relationName()}(): BelongsTo
    {
        return \$this->belongsTo(\\App\\Models\\Forms\\{$field->lookupForm->model_class}::class, '{$field->name}');
    }
PHP)
            ->implode("\n");

        $connection = $form->database === FormConnection::Central ? "\n    protected \$connection = 'central';\n" : '';

        Files::put(static::basePath($form), <<<PHP
<?php

namespace App\Forms\Generated\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Formulário "{$form->title}".
 *
 * GERADO pelo construtor de formulários: recriado sempre que o formulário é
 * salvo. Personalize em App\Models\Forms\\{$form->model_class}.
 */
abstract class {$form->model_class}Base extends Model
{{$connection}
    protected \$table = '{$form->table_name}';

    protected \$fillable = [
{$fillable}
    ];

    protected \$hidden = [
{$hidden}
    ];

    protected function casts(): array
    {
        return [
{$casts}
        ];
    }
{$relations}
}

PHP);

        Files::putIfMissing(static::path($form), <<<PHP
<?php

namespace App\Models\Forms;

use App\Forms\Generated\Models\\{$form->model_class}Base;

/**
 * Model do formulário "{$form->title}". Criado uma vez pelo construtor de
 * formulários; pode personalizar à vontade (a estrutura vem da classe base).
 */
class {$form->model_class} extends {$form->model_class}Base
{
}

PHP);
    }

    public static function delete(FormDefinition $form): void
    {
        Files::delete(static::basePath($form), static::path($form));
    }
}
