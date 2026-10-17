<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'panel',
        'item',
        'group',
        'label',
        'icon',
        'sort',
        'hidden',
    ];

    protected $casts = [
        'hidden' => 'boolean',
    ];
}
