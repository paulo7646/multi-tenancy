<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class FilialScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->hasUser()) {
            $filialId = auth()->user()->getActiveFilialId();
            
            if ($filialId) {
                $builder->where(
                    $model->getTable() . '.filial_id',
                    $filialId
                );
            }
        }
    }
}
