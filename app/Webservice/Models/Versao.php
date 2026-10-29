<?php

namespace App\Webservice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versão do layout (EDI) de uma operação. Vale entre o início da vigência e a expiração.
 */
class Versao extends Model
{
    protected $table = 'ws_versoes';

    protected $attributes = ['ativo' => true];

    protected $fillable = ['operacao_id', 'versao', 'inicio_vigencia', 'expira_em', 'ativo'];

    protected function casts(): array
    {
        return ['inicio_vigencia' => 'date', 'expira_em' => 'date', 'ativo' => 'boolean'];
    }

    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class, 'operacao_id');
    }

    public function objetos(): HasMany
    {
        return $this->hasMany(Objeto::class, 'versao_id')->orderBy('ordem')->orderBy('id');
    }

    public function vigente(): bool
    {
        return $this->ativo
            && (! $this->inicio_vigencia || $this->inicio_vigencia->startOfDay()->lte(now()))
            && (! $this->expira_em || $this->expira_em->endOfDay()->gte(now()));
    }
}
