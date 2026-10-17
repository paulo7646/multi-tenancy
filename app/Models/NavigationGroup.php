<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationGroup extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'panel',
        'name',
        'sort',
    ];
}
