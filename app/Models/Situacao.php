<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Situacao extends Model
{
    /** @use HasFactory<\Database\Factories\SituacaoFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nome',
        'icon',
        'color',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
