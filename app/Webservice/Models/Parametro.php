<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parâmetro enviado na chamada: na URL (/rota/{nome}), na query, no corpo ou no header.
 */
class Parametro extends Model
{
    public const LOCAIS = [
        'url' => 'Na URL (substitui {nome})',
        'query' => 'Query string (?nome=valor)',
        'corpo' => 'Corpo da requisição',
        'header' => 'Header',
    ];

    protected $table = 'ws_parametros';

    protected $fillable = ['operacao_id', 'nome', 'valor', 'local', 'obrigatorio'];

    protected function casts(): array
    {
        return ['obrigatorio' => 'boolean'];
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }
}
