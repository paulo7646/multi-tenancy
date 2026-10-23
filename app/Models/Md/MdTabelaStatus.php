<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Situação (md_status) habilitada numa tabela.
 */
class MdTabelaStatus extends Model
{
    protected $connection = 'central';

    protected $table = 'md_tabela_status';

    protected $fillable = ['md_tabela_id', 'md_status_id', 'padrao', 'ordem'];

    protected function casts(): array
    {
        return ['padrao' => 'boolean', 'ordem' => 'integer'];
    }

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'md_tabela_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(MdStatus::class, 'md_status_id');
    }
}
