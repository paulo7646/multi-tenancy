<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liga um atributo do retorno (nome_json) à coluna da tabela do evento.
 */
class Atributo extends Model
{
    protected $table = 'ws_atributos';

    protected $fillable = ['evento_id', 'nome_json', 'campo'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }
}
