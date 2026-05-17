<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Adicione esta trait a qualquer model que precise ser filtrado por filial.
 * O model deve ter a coluna filial_id.
 */
trait HasFilialScope
{
    public static function bootHasFilialScope(): void
    {
        static::addGlobalScope('filial', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();
            $filialId = $user->getActiveFilialId();

            if ($filialId !== null) {
                $builder->where(static::getFilialColumn(), $filialId);
            }
        });
    }

    protected static function getFilialColumn(): string
    {
        return 'filial_id';
    }

    public function scopeDeFilial(Builder $query, int $filialId): Builder
    {
        return $query->where(static::getFilialColumn(), $filialId);
    }

    public function scopeTodasFiliais(Builder $query): Builder
    {
        return $query->withoutGlobalScope('filial');
    }
}
