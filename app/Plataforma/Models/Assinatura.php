<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido de assinatura de um documento (PDF) por uma ou mais pessoas.
 */
class Assinatura extends Model
{
    public const STATUS = [
        'rascunho' => 'Rascunho',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'expirado' => 'Expirado',
    ];

    protected $table = 'ass_documentos';

    protected $attributes = ['status' => 'rascunho', 'ordem_obrigatoria' => true, 'exigir_codigo' => true];

    protected $fillable = [
        'titulo', 'versao_id', 'hash_documento', 'status', 'ordem_obrigatoria', 'exigir_codigo', 'expira_em',
        'codigo_verificacao', 'tabela', 'registro_id', 'certificado_id', 'concluido_em', 'cancelado_em',
        'motivo_cancelamento', 'user_id', 'criado_por',
    ];

    protected function casts(): array
    {
        return [
            'ordem_obrigatoria' => 'boolean',
            'exigir_codigo' => 'boolean',
            'expira_em' => 'datetime',
            'concluido_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    public function versao(): BelongsTo
    {
        return $this->belongsTo(Versao::class, 'versao_id');
    }

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class, 'certificado_id');
    }

    public function assinantes(): HasMany
    {
        return $this->hasMany(Assinante::class, 'documento_id')->orderBy('ordem')->orderBy('id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAssinatura::class, 'documento_id')->orderBy('id');
    }

    public function aberta(): bool
    {
        return $this->status === 'em_andamento' && ! $this->expirou();
    }

    public function expirou(): bool
    {
        return $this->expira_em !== null && $this->expira_em->isPast();
    }
}
