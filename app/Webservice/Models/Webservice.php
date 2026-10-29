<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Webservice consumido ou disponibilizado pelo cliente (ou pela base pai).
 */
class Webservice extends Model
{
    public const TIPOS = [
        'consumir' => 'Consumir (chamo o serviço de terceiros)',
        'disponibilizar' => 'Disponibilizar (terceiros chamam o meu)',
    ];

    public const AUTENTICACOES = [
        'nenhuma' => 'Sem autenticação',
        'basic' => 'Basic (usuário e senha)',
        'bearer' => 'Bearer token',
        'apikey' => 'API key (cabeçalho)',
        'oauth2' => 'OAuth2 (client credentials)',
    ];

    protected $table = 'webservices';

    protected $attributes = ['tipo' => 'consumir', 'autenticacao' => 'nenhuma', 'ativo' => true];

    protected $fillable = ['nome', 'tipo', 'host', 'autenticacao', 'credenciais', 'headers', 'ativo'];

    protected function casts(): array
    {
        return ['credenciais' => 'encrypted:array', 'headers' => 'array', 'ativo' => 'boolean'];
    }

    public function operacoes(): HasMany
    {
        return $this->hasMany(Operacao::class, 'webservice_id');
    }
}
