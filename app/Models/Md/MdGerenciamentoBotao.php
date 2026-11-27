<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Botão do datagrid, sempre no topo:
 *  - sem seleção: age sem registro;
 *  - exige seleção: age no registro marcado, ou em vários se for multioperação ("multiplos");
 *  - com "grupo": fica dentro do grupo de botões do gerenciamento (grupos_botoes).
 */
class MdGerenciamentoBotao extends Model
{
    protected $connection = 'central';

    protected $table = 'md_gerenciamento_botoes';

    protected $fillable = [
        'md_gerenciamento_id', 'nome', 'titulo', 'nome_fisico', 'nome_arquivo', 'icone', 'cor',
        'exige_selecao', 'multiplos', 'grupo', 'confirmar', 'ordem',
    ];

    protected function casts(): array
    {
        return [
            'exige_selecao' => 'boolean',
            'multiplos' => 'boolean',
            'confirmar' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function gerenciamento(): BelongsTo
    {
        return $this->belongsTo(MdGerenciamento::class, 'md_gerenciamento_id');
    }

    /**
     * Assinatura do método na classe de botões: vários selecionados (multioperação), um selecionado ou nenhum.
     */
    public function assinatura(string $model): string
    {
        return match (true) {
            $this->exige_selecao && $this->multiplos => 'Collection $registros',
            $this->exige_selecao => "{$model} \$registro",
            default => '',
        };
    }
}
