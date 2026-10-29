<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * O que acontece quando o objeto é processado: grava na tabela (incluir, alterar,
 * incluir se não existir, excluir) ou chama uma função do controller da tabela.
 */
class Evento extends Model
{
    public const TIPOS = [
        'incluir' => 'Incluir',
        'alterar' => 'Alterar',
        'incluir_se_nao_existir' => 'Incluir se não existir',
        'excluir' => 'Excluir',
        'codigo_fonte' => 'Código-fonte (função do controller)',
    ];

    protected $table = 'ws_eventos';

    protected $attributes = ['ativo' => true, 'ordem' => 0];

    protected $fillable = ['objeto_id', 'tipo', 'tabela', 'funcao', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'ordem' => 'integer'];
    }

    public function objeto(): BelongsTo
    {
        return $this->belongsTo(Objeto::class, 'objeto_id');
    }

    public function busca(): HasMany
    {
        return $this->hasMany(Busca::class, 'evento_id');
    }

    public function atributos(): HasMany
    {
        return $this->hasMany(Atributo::class, 'evento_id');
    }
}
