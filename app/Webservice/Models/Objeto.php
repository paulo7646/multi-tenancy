<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nó da árvore do retorno. "root" é o {} principal; os demais têm o nome que vem
 * no JSON/XML (se não vier e for obrigatório, a integração acusa erro).
 */
class Objeto extends Model
{
    protected $table = 'ws_objetos';

    protected $attributes = ['lista' => false, 'obrigatorio' => true, 'novo_lote' => false, 'ordem' => 0];

    protected $fillable = ['versao_id', 'pai_id', 'nome', 'lista', 'obrigatorio', 'novo_lote', 'ordem'];

    protected function casts(): array
    {
        return ['lista' => 'boolean', 'obrigatorio' => 'boolean', 'novo_lote' => 'boolean', 'ordem' => 'integer'];
    }

    public function versao(): BelongsTo
    {
        return $this->belongsTo(Versao::class, 'versao_id');
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pai_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(self::class, 'pai_id')->orderBy('ordem')->orderBy('id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'objeto_id')->orderBy('ordem')->orderBy('id');
    }
}
