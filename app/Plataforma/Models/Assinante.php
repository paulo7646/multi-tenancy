<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assinante extends Model
{
    public const STATUS = ['pendente' => 'Pendente', 'assinou' => 'Assinou', 'recusou' => 'Recusou'];

    protected $table = 'ass_assinantes';

    protected $attributes = ['status' => 'pendente', 'ordem' => 1, 'tentativas' => 0];

    protected $fillable = [
        'documento_id', 'user_id', 'nome', 'email', 'identificacao', 'papel', 'ordem', 'status', 'token_hash', 'token_expira',
        'codigo_hash', 'codigo_expira', 'tentativas', 'notificado_em', 'assinado_em', 'ip', 'user_agent', 'hash_no_ato', 'motivo_recusa',
    ];

    protected $hidden = ['token_hash', 'codigo_hash'];

    protected function casts(): array
    {
        return [
            'token_expira' => 'datetime',
            'codigo_expira' => 'datetime',
            'notificado_em' => 'datetime',
            'assinado_em' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class, 'documento_id');
    }
}
