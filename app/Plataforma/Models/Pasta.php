<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pasta extends Model
{
    protected $table = 'doc_pastas';

    protected $fillable = ['pai_id', 'nome'];

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pai_id');
    }

    public function filhas(): HasMany
    {
        return $this->hasMany(self::class, 'pai_id');
    }

    public function arquivos(): HasMany
    {
        return $this->hasMany(Arquivo::class, 'pasta_id');
    }

    /**
     * Caminho completo: Contratos / 2026 / Fornecedores.
     */
    public function caminho(): string
    {
        $partes = [$this->nome];

        for ($pasta = $this->pai, $i = 0; $pasta && $i < 20; $pasta = $pasta->pai, $i++) {
            array_unshift($partes, $pasta->nome);
        }

        return implode(' / ', $partes);
    }

    /**
     * @return array<int, string> id => caminho, para selects
     */
    public static function opcoes(?int $exceto = null): array
    {
        return static::with('pai.pai.pai')->get()
            ->reject(fn (self $p) => $exceto !== null && $p->getKey() === $exceto)
            ->mapWithKeys(fn (self $p) => [$p->getKey() => $p->caminho()])
            ->sort()
            ->all();
    }
}
