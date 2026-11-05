<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liga um documento a um registro de qualquer tabela do dicionário.
 */
class Anexo extends Model
{
    protected $table = 'doc_anexos';

    protected $fillable = ['arquivo_id', 'tabela', 'registro_id', 'user_id'];

    public function arquivo(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class, 'arquivo_id');
    }
}
