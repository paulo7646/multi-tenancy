<?php

namespace App\Models;

use App\Forms\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FormField extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'uid',
        'form_definition_id',
        'name',
        'label',
        'type',
        'length',
        'precision',
        'scale',
        'options',
        'nullable',
        'required',
        'unique',
        'index',
        'default',
        'help',
        'show_in_table',
        'searchable',
        'sortable',
        'lookup_form_id',
        'lookup_display',
        'on_delete',
        'sort',
    ];

    protected $casts = [
        'type' => FieldType::class,
        'options' => 'array',
        'nullable' => 'boolean',
        'required' => 'boolean',
        'unique' => 'boolean',
        'index' => 'boolean',
        'show_in_table' => 'boolean',
        'searchable' => 'boolean',
        'sortable' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (FormField $field) {
            $field->uid ??= (string) Str::uuid();
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    public function lookupForm(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'lookup_form_id');
    }

    /**
     * Nome do relacionamento belongsTo no model: cliente_id -> cliente.
     */
    public function relationName(): string
    {
        return Str::camel(Str::beforeLast($this->name, '_id'));
    }

    /**
     * Coluna do formulário de referência exibida no lookup.
     */
    public function lookupDisplayColumn(): string
    {
        return $this->lookup_display ?: ($this->lookupForm?->lookupColumn() ?? 'id');
    }
}
