<?php

namespace App\Models\Traits;

use App\Models\Scopes\FilialScope;

trait HasFilialScope
{
    protected static function bootHasFilialScope(): void
    {
        static::addGlobalScope(new FilialScope);
    }
}   
