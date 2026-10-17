<?php

namespace App\Models;

use App\Forms\Enums\FilterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormFilter extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'form_definition_id',
        'label',
        'field',
        'type',
        'sort',
    ];

    protected $casts = [
        'type' => FilterType::class,
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }
}
