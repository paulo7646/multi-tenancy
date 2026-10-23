<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tradução de uma tabela: os campos (uids, em ordem) que representam um
 * registro dela quando outra tabela aponta para ela (FK).
 *  - tabela: nas colunas dos gerenciamentos;
 *  - lookup: nos campos dos formulários.
 */
class MdTabelaTraducao extends Model
{
    public const TABELA = 'tabela';
    public const LOOKUP = 'lookup';

    protected $connection = 'central';

    protected $table = 'md_tabela_traducoes';

    protected $fillable = ['md_tabela_id', 'tipo', 'nome', 'campos', 'padrao', 'ordem'];

    protected function casts(): array
    {
        return ['campos' => 'array', 'padrao' => 'boolean', 'ordem' => 'integer'];
    }

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'md_tabela_id');
    }
}
