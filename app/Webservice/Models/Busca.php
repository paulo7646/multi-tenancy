<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Condição para localizar o registro da tabela do evento (campo operador valor).
 */
class Busca extends Model
{
    public const OPERADORES = ['=' => '=', '<>' => '<>', '>' => '>', '>=' => '>=', '<' => '<', '<=' => '<=', 'like' => 'contém'];

    protected $table = 'ws_evento_busca';

    protected $fillable = ['evento_id', 'campo', 'operador', 'valor'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class, 'evento_id');
    }
}
