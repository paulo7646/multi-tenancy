<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Licenca extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descricao',
        'icon',
        'color',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
