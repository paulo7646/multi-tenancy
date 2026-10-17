<?php

namespace App\Forms\Generators;

use App\Forms\Enums\ButtonAction;
use App\Forms\Enums\ButtonPosition;
use App\Forms\Enums\FieldType;
use App\Forms\Enums\FilterType;
use App\Models\FormButton;
use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gera a tela do Filament do formulário em código explícito:
 *  - app/Forms/Generated/Resources/{Central|Tenant}/{Resource}Base.php
 *      recriado a cada geração (form, table, filtros, botões);
 *  - {pasta de resources do painel}/Forms/{Resource}.php + Pages/
 *      criados uma vez, para você personalizar (sobrescreva métodos da base).
 */
class ResourceWriter
{
    public const PAGES = [
        'Listar' => 'App\\Forms\\Pages\\GeneratedListRecords',
        'Criar' => 'App\\Forms\\Pages\\GeneratedCreateRecord',
        'Editar' => 'App\\Forms\\Pages\\GeneratedEditRecord',
    ];

    public static function baseNamespace(FormDefinition $form): string
    {
        return 'App\\Forms\\Generated\\Resources\\' . Str::studly($form->database->value);
    }

    public static function basePath(FormDefinition $form): string
    {
        return app_path('Forms/Generated/Resources/' . Str::studly($form->database->value) . "/{$form->resource_class}Base.php");
    }

    public static function path(FormDefinition $form): string
    {
        return $form->database->resourcePath() . "/{$form->resource_class}.php";
    }

    public static function pagesPath(FormDefinition $form): string
    {
        return $form->database->resourcePath() . "/{$form->resource_class}";
    }

    public static function write(FormDefinition $form): void
    {
        $form->load(['fields.lookupForm', 'buttons', 'filters']);

        Files::put(static::basePath($form), static::base($form));

        $namespace = $form->database->resourceNamespace();

        Files::putIfMissing(static::path($form), strtr(<<<'PHP'
<?php

namespace {namespace};

use {baseNamespace}\{resource}Base;

/**
 * Tela do formulário "{title}". Criada uma vez pelo construtor de formulários.
 * Formulário, tabela, filtros e botões vêm da classe base (recriada a cada
 * alteração); sobrescreva métodos aqui para personalizar.
 */
class {resource} extends {resource}Base
{
}

PHP, [
            '{namespace}' => $namespace,
            '{baseNamespace}' => static::baseNamespace($form),
            '{resource}' => $form->resource_class,
            '{title}' => $form->title,
        ]));

        foreach (static::PAGES as $page => $base) {
            Files::putIfMissing(static::pagesPath($form) . "/Pages/{$page}.php", strtr(<<<'PHP'
<?php

namespace {namespace}\{resource}\Pages;

use {base};
use {namespace}\{resource};

class {page} extends {baseClass}
{
    protected static string $resource = {resource}::class;
}

PHP, [
                '{namespace}' => $namespace,
                '{resource}' => $form->resource_class,
                '{base}' => $base,
                '{baseClass}' => class_basename($base),
                '{page}' => $page,
            ]));
        }
    }

    public static function delete(FormDefinition $form): void
    {
        Files::delete(static::basePath($form), static::path($form), static::pagesPath($form));
    }

    // ------------------------------------------------------------------
    // Classe base
    // ------------------------------------------------------------------

    protected static function base(FormDefinition $form): string
    {
        $fields = $form->fields;
        $controller = '\\' . $form->controllerFqcn();

        return strtr(<<<'PHP'
<?php

namespace {baseNamespace};

use App\Forms\Concerns\IsFormResource;
use App\Forms\Rules\Cnpj;
use App\Forms\Rules\Cpf;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Formulário "{title}".
 *
 * GERADO pelo construtor de formulários: recriado sempre que o formulário é
 * salvo. Personalize em {resourceFqcn}.
 */
abstract class {resource}Base extends Resource
{
    use IsFormResource;

    protected static string $formTable = '{table}';

    protected static ?string $model = \App\Models\Forms\{model}::class;

    protected static ?string $modelLabel = {name};

    protected static ?string $pluralModelLabel = {titleExport};

    protected static ?string $navigationLabel = {titleExport};

    protected static string | \UnitEnum | null $navigationGroup = {group};

    protected static string | \BackedEnum | null $navigationIcon = {icon};

    protected static ?string $recordTitleAttribute = {lookup};

    protected static ?string $slug = '{slug}';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
{components}
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
{columns}
            ])
            ->filters([
{filters}
            ])
            ->recordActions([
{recordButtons}
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
{bulkButtons}
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Botões do topo da listagem.
     */
    public static function headerActions(): array
    {
        return [
{headerButtons}
            CreateAction::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \{pagesNamespace}\Listar::route('/'),
            'create' => \{pagesNamespace}\Criar::route('/create'),
            'edit' => \{pagesNamespace}\Editar::route('/{record}/edit'),
        ];
    }
}

PHP, [
            '{baseNamespace}' => static::baseNamespace($form),
            '{resource}' => $form->resource_class,
            '{resourceFqcn}' => $form->resourceFqcn(),
            '{pagesNamespace}' => $form->resourceFqcn() . '\\Pages',
            '{model}' => $form->model_class,
            '{table}' => $form->table_name,
            '{slug}' => Str::slug($form->table_name),
            '{title}' => $form->title,
            '{name}' => static::e($form->name),
            '{titleExport}' => static::e($form->title),
            '{group}' => static::e($form->navigation_group ?: null),
            '{icon}' => static::e($form->navigation_icon ?: 'heroicon-o-document-text'),
            '{lookup}' => static::e($form->lookup_field ?: null),
            '{components}' => static::lines($fields->map(fn (FormField $field) => static::component($field)), 16),
            '{columns}' => static::lines($fields->filter(fn (FormField $field) => $field->show_in_table && $field->type !== FieldType::Password)->map(fn (FormField $field) => static::column($field)), 16),
            '{filters}' => static::lines($form->filters->map(fn (FormFilter $filter) => static::filter($filter, $fields))->filter(), 16),
            '{recordButtons}' => static::lines(static::buttons($form->buttons, ButtonPosition::Record, $controller), 16),
            '{bulkButtons}' => static::lines(static::buttons($form->buttons, ButtonPosition::Bulk, $controller), 20),
            '{headerButtons}' => static::lines(static::buttons($form->buttons, ButtonPosition::Header, $controller), 12),
        ]);
    }

    // ------------------------------------------------------------------
    // Campos do formulário
    // ------------------------------------------------------------------

    protected static function component(FormField $field): string
    {
        $type = $field->type;
        $name = static::e($field->name);
        $length = $field->length ?: $type->defaultLength();

        $code = match (true) {
            $type->isForeign() => "Select::make({$name})->relationship(" . static::e($field->relationName()) . ', ' . static::e($field->lookupDisplayColumn()) . ')->searchable()->preload()',
            $type === FieldType::Enum => "Select::make({$name})->options(" . static::options($field) . ')',
            $type === FieldType::MultiSelect => "Select::make({$name})->multiple()->options(" . static::options($field) . ')',
            $type === FieldType::Boolean => "Toggle::make({$name})",
            $type->isDate() => "DatePicker::make({$name})",
            $type->isDateTime() => "DateTimePicker::make({$name})",
            $type->isTime() => "TimePicker::make({$name})",
            $type->isJson() => "KeyValue::make({$name})->columnSpanFull()",
            $type === FieldType::LongText => "RichEditor::make({$name})->columnSpanFull()",
            in_array($type, [FieldType::Text, FieldType::TinyText, FieldType::MediumText], true) => "Textarea::make({$name})->rows(4)->columnSpanFull()",
            $type === FieldType::Color => "ColorPicker::make({$name})",
            $type === FieldType::Image => "FileUpload::make({$name})->image()->disk('public')->directory('forms/{$field->form->table_name}')",
            $type === FieldType::File => "FileUpload::make({$name})->disk('public')->directory('forms/{$field->form->table_name}')",
            $type === FieldType::Password => "TextInput::make({$name})->password()->revealable()->maxLength(255)"
                . '->formatStateUsing(fn () => null)->dehydrated(fn ($state) => filled($state))',
            $type === FieldType::Email => "TextInput::make({$name})->email()->maxLength({$length})",
            $type === FieldType::Url => "TextInput::make({$name})->url()->maxLength({$length})",
            $type === FieldType::Phone => "TextInput::make({$name})->tel()->mask('(99) 99999-9999')",
            $type === FieldType::Cpf => "TextInput::make({$name})->mask('999.999.999-99')->rule(new Cpf())",
            $type === FieldType::Cnpj => "TextInput::make({$name})->mask('99.999.999/9999-99')->rule(new Cnpj())",
            $type === FieldType::Cep => "TextInput::make({$name})->mask('99999-999')",
            $type->isNumeric() => "TextInput::make({$name})->numeric()"
                . ($type->isInteger() ? '->integer()' : "->step('any')")
                . ($type->isUnsigned() ? '->minValue(0)' : '')
                . ($type === FieldType::Year ? '->minValue(1901)->maxValue(2155)' : ''),
            $type === FieldType::Uuid => "TextInput::make({$name})->uuid()",
            $type === FieldType::Ulid => "TextInput::make({$name})->ulid()",
            $type === FieldType::IpAddress => "TextInput::make({$name})->ip()",
            $type === FieldType::MacAddress => "TextInput::make({$name})->macAddress()",
            default => "TextInput::make({$name})->maxLength({$length})",
        };

        $code .= '->label(' . static::e($field->label) . ')';

        if ($field->required) {
            // senha: obrigatória só ao criar (na edição, vazio mantém a atual)
            $code .= $type === FieldType::Password ? "->required(fn (string \$operation) => \$operation === 'create')" : '->required()';
        }

        if ($field->help) {
            $code .= '->helperText(' . static::e($field->help) . ')';
        }

        if (filled($field->default) && ! $type->isForeign() && ! $type->isUpload() && $type !== FieldType::Password) {
            $code .= '->default(' . static::e(\App\Forms\Schema\SchemaSnapshot::castDefault($type, $field->default)) . ')';
        }

        if ($field->unique && ! in_array($type, [FieldType::Boolean, FieldType::MultiSelect], true) && ! $type->isJson()) {
            $code .= '->unique(ignoreRecord: true)';
        }

        return $code . ',';
    }

    // ------------------------------------------------------------------
    // Colunas da listagem
    // ------------------------------------------------------------------

    protected static function column(FormField $field): string
    {
        $type = $field->type;
        $name = static::e($field->name);

        $code = match (true) {
            $type === FieldType::Boolean => "IconColumn::make({$name})->boolean()",
            $type->isForeign() => 'TextColumn::make(' . static::e($field->relationName() . '.' . $field->lookupDisplayColumn()) . ')',
            $type->isDate() => "TextColumn::make({$name})->date('d/m/Y')",
            $type->isDateTime() => "TextColumn::make({$name})->dateTime('d/m/Y H:i')",
            $type->isJson() => "TextColumn::make({$name})->limit(40)",
            $type === FieldType::Image => "ImageColumn::make({$name})->disk('public')",
            $type === FieldType::Color => "ColorColumn::make({$name})",
            $type->hasChoices() => "TextColumn::make({$name})->badge()",
            $type->isNumeric() && $type !== FieldType::Year => "TextColumn::make({$name})->numeric()",
            default => "TextColumn::make({$name})->limit(50)",
        };

        $code .= '->label(' . static::e($field->label) . ')';

        if ($field->searchable && ! in_array($type, [FieldType::Boolean, FieldType::Image, FieldType::Color], true)) {
            $code .= '->searchable()';
        }

        if ($field->sortable && ! $type->isForeign()) {
            $code .= '->sortable()';
        }

        return $code . ',';
    }

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    protected static function filter(FormFilter $filter, Collection $fields): ?string
    {
        /** @var FormField|null $field */
        $field = $fields->firstWhere('name', $filter->field);

        if (! $field) {
            return null;
        }

        $key = static::e("filtro_{$filter->id}");
        $label = static::e($filter->label);
        $column = static::e($field->name);

        return match ($filter->type) {
            FilterType::Ternary => "TernaryFilter::make({$key})->label({$label})->attribute({$column}),",

            FilterType::Select => match (true) {
                $field->type->isForeign() => "SelectFilter::make({$key})->label({$label})->relationship("
                    . static::e($field->relationName()) . ', ' . static::e($field->lookupDisplayColumn()) . ')->searchable()->preload(),',
                $field->type === FieldType::MultiSelect => "SelectFilter::make({$key})->label({$label})->options(" . static::options($field) . ')'
                    . "->query(fn (Builder \$query, array \$data) => \$query->when(\$data['value'] ?? null, fn (Builder \$q, \$value) => \$q->whereJsonContains({$column}, \$value))),",
                $field->type->hasChoices() => "SelectFilter::make({$key})->label({$label})->attribute({$column})->options(" . static::options($field) . '),',
                default => "SelectFilter::make({$key})->label({$label})->attribute({$column})->searchable()"
                    . "->options(fn () => static::getModel()::query()->whereNotNull({$column})->distinct()->orderBy({$column})->limit(500)->pluck({$column}, {$column})->all()),",
            },

            FilterType::DateRange => "Filter::make({$key})->label({$label})->schema([\n"
                . "                    DatePicker::make('de')->label(" . static::e("{$filter->label} de") . "),\n"
                . "                    DatePicker::make('ate')->label(" . static::e("{$filter->label} até") . "),\n"
                . "                ])->query(fn (Builder \$query, array \$data) => \$query\n"
                . "                    ->when(\$data['de'] ?? null, fn (Builder \$q, \$date) => \$q->whereDate({$column}, '>=', \$date))\n"
                . "                    ->when(\$data['ate'] ?? null, fn (Builder \$q, \$date) => \$q->whereDate({$column}, '<=', \$date))),",

            FilterType::Text => "Filter::make({$key})->label({$label})->schema([TextInput::make('valor')->label({$label})])"
                . "->query(fn (Builder \$query, array \$data) => \$query->when(\$data['valor'] ?? null, fn (Builder \$q, \$value) => \$q->where({$column}, 'ilike', \"%{\$value}%\"))),",
        };
    }

    // ------------------------------------------------------------------
    // Botões
    // ------------------------------------------------------------------

    /**
     * @param  Collection<int, FormButton>  $buttons
     * @return Collection<int, string>
     */
    protected static function buttons(Collection $buttons, ButtonPosition $position, string $controller): Collection
    {
        return $buttons
            ->filter(fn (FormButton $button) => $button->position === $position)
            ->map(function (FormButton $button) use ($position, $controller) {
                $class = $position === ButtonPosition::Bulk ? 'BulkAction' : 'Action';
                $label = static::e($button->label);

                $code = "{$class}::make(" . static::e($button->method) . ")->label({$label})->color(" . static::e($button->color->value) . ')';

                if ($button->icon) {
                    $code .= '->icon(' . static::e($button->icon) . ')';
                }

                if ($button->requires_confirmation) {
                    $code .= '->requiresConfirmation()';
                }

                if ($button->action === ButtonAction::Url) {
                    $url = static::e((string) $button->url);

                    return $code . match ($position) {
                        ButtonPosition::Record => "->url(fn (Model \$record) => str_replace('{id}', (string) \$record->getKey(), {$url}))->openUrlInNewTab(),",
                        ButtonPosition::Header => "->url({$url})->openUrlInNewTab(),",
                        ButtonPosition::Bulk => "->action(fn (Component \$livewire) => \$livewire->redirect({$url})),",
                    };
                }

                $method = $button->method;

                return $code . match ($position) {
                    ButtonPosition::Record => "->action(fn (Model \$record, Component \$livewire) => static::runButton(\$livewire, {$label}, fn () => app({$controller}::class)->{$method}(\$record))),",
                    ButtonPosition::Header => "->action(fn (Component \$livewire) => static::runButton(\$livewire, {$label}, fn () => app({$controller}::class)->{$method}())),",
                    ButtonPosition::Bulk => "->action(fn (Collection \$records, Component \$livewire) => static::runButton(\$livewire, {$label}, fn () => app({$controller}::class)->{$method}(\$records))),",
                };
            })
            ->values();
    }

    // ------------------------------------------------------------------

    protected static function options(FormField $field): string
    {
        return static::e(collect($field->options ?? [])->mapWithKeys(fn ($option) => [(string) $option => (string) $option])->all());
    }

    /**
     * Valor PHP literal (strings sempre escapadas por var_export).
     */
    protected static function e(mixed $value): string
    {
        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $items[] = var_export($key, true) . ' => ' . static::e($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        return var_export($value, true);
    }

    /**
     * @param  Collection<int, string>|iterable<string>  $lines
     */
    protected static function lines(iterable $lines, int $indent): string
    {
        $pad = str_repeat(' ', $indent);

        return collect($lines)->map(fn (string $line) => $pad . str_replace("\n", "\n", $line))->implode("\n");
    }
}
