<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento da trilha de auditoria da assinatura. Só se inclui; nunca se altera.
 */
class EventoAssinatura extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ass_eventos';

    protected $fillable = ['documento_id', 'assinante_id', 'tipo', 'descricao', 'ip', 'user_agent', 'dados', 'hash_anterior', 'hash', 'created_at'];

    protected function casts(): array
    {
        return ['dados' => 'array', 'created_at' => 'datetime'];
    }

    public function assinante(): BelongsTo
    {
        return $this->belongsTo(Assinante::class, 'assinante_id');
    }

    /**
     * Hash do evento: encadeia o anterior, o documento, o tipo, o momento e os dados.
     */
    public static function calcularHash(?string $anterior, int $documentoId, string $tipo, string $momento, ?array $dados): string
    {
        return hash('sha256', implode('|', [$anterior ?? '', $documentoId, $tipo, $momento, json_encode($dados ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]));
    }
}
