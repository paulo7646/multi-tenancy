<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lote de uma integração: uma transação do banco. Se der erro, é desfeita inteira.
 */
class Lote extends Model
{
    protected $table = 'ws_lotes';

    protected $fillable = ['integracao_id', 'objeto_id', 'numero', 'status', 'mensagem'];

    public function integracao(): BelongsTo
    {
        return $this->belongsTo(Integracao::class, 'integracao_id');
    }

    public function objeto(): BelongsTo
    {
        return $this->belongsTo(Objeto::class, 'objeto_id');
    }
}
