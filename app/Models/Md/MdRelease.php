<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pacote publicado de um módulo: o dicionário dele e as migrations (arquivo e hash). Cada empresa
 * aplica só as releases dos módulos que tem.
 */
class MdRelease extends Model
{
    protected $connection = 'central';

    protected $table = 'md_releases';

    protected $fillable = ['modulo_id', 'versao', 'notas', 'pacote', 'hash', 'publicado_por', 'publicado_em'];

    protected function casts(): array
    {
        return [
            'pacote' => 'array',
            'versao' => 'integer',
            'publicado_em' => 'datetime',
        ];
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    /**
     * Migrations do pacote: [{arquivo, hash}].
     *
     * @return list<array{arquivo: string, hash: string}>
     */
    public function migrations(): array
    {
        return $this->pacote['migrations'] ?? [];
    }
}
