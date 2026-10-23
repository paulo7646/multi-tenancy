<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Item de uma tabela lista (código sequencial) ou conteúdo (valor definido
 * pelo usuário). Vira um caso do enum do módulo e um registro da tabela.
 */
class MdTabelaItem extends Model
{
    protected $connection = 'central';

    protected $table = 'md_tabela_itens';

    protected $fillable = ['md_tabela_id', 'codigo', 'valor', 'descricao', 'ordem'];

    protected function casts(): array
    {
        return ['codigo' => 'integer', 'ordem' => 'integer'];
    }

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'md_tabela_id');
    }

    /**
     * Nome do caso no enum: "Em análise" -> EM_ANALISE.
     */
    public static function nomeDoCaso(string $descricao): string
    {
        $nome = Str::of(Str::ascii($descricao))->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->toString();

        return $nome === '' ? 'ITEM' : (ctype_digit($nome[0]) ? "_{$nome}" : $nome);
    }
}
