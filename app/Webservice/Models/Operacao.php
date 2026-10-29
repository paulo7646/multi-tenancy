<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Serviço de um webservice: host + rota + método (ex.: GET /consulta/teste).
 */
class Operacao extends Model
{
    public const METODOS = ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'];

    public const FORMATOS = ['json' => 'JSON', 'xml' => 'XML'];

    protected $table = 'ws_operacoes';

    protected $attributes = ['metodo' => 'GET', 'formato' => 'json', 'timeout' => 30, 'ativo' => true];

    protected $fillable = ['webservice_id', 'nome', 'rota', 'metodo', 'formato', 'timeout', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'timeout' => 'integer'];
    }

    public function webservice(): BelongsTo
    {
        return $this->belongsTo(Webservice::class, 'webservice_id');
    }

    public function parametros(): HasMany
    {
        return $this->hasMany(Parametro::class, 'operacao_id');
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(Versao::class, 'operacao_id');
    }

    public function url(): string
    {
        return rtrim((string) $this->webservice?->host, '/') . '/' . ltrim($this->rota, '/');
    }
}
