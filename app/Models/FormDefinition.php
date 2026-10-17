<?php

namespace App\Models;

use App\Forms\Enums\FormConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Formulário do construtor. A partir dele são gerados migration, model,
 * controller e resource do Filament (ver App\Forms\FormGenerator).
 */
class FormDefinition extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'name',
        'title',
        'database',
        'table_name',
        'model_class',
        'controller_class',
        'resource_class',
        'navigation_group',
        'navigation_icon',
        'lookup_field',
        'roles',
        'generated_schema',
        'generated_at',
    ];

    protected $casts = [
        'database' => FormConnection::class,
        'roles' => 'array',
        'generated_schema' => 'array',
        'generated_at' => 'datetime',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort');
    }

    public function buttons(): HasMany
    {
        return $this->hasMany(FormButton::class)->orderBy('sort');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(FormFilter::class)->orderBy('sort');
    }

    /**
     * Campos de outros formulários que apontam para este.
     */
    public function referencedBy(): HasMany
    {
        return $this->hasMany(FormField::class, 'lookup_form_id');
    }

    public function isGenerated(): bool
    {
        return $this->generated_at !== null;
    }

    public function modelFqcn(): string
    {
        return 'App\\Models\\Forms\\' . $this->model_class;
    }

    public function controllerFqcn(): string
    {
        return 'App\\Http\\Controllers\\Forms\\' . $this->controller_class;
    }

    public function resourceFqcn(): string
    {
        return $this->database->resourceNamespace() . '\\' . $this->resource_class;
    }

    /**
     * Coluna exibida quando outro formulário aponta para este.
     */
    public function lookupColumn(): string
    {
        return $this->lookup_field ?: 'id';
    }
}
