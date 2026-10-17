<?php

namespace App\Models;

use App\Forms\Enums\ButtonAction;
use App\Forms\Enums\ButtonColor;
use App\Forms\Enums\ButtonPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormButton extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'form_definition_id',
        'label',
        'method',
        'icon',
        'color',
        'position',
        'action',
        'url',
        'requires_confirmation',
        'sort',
    ];

    protected $casts = [
        'color' => ButtonColor::class,
        'position' => ButtonPosition::class,
        'action' => ButtonAction::class,
        'requires_confirmation' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }
}
