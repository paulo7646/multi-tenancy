<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Botão do datagrid:
 *  - no_registro: na coluna de ações de cada registro;
 *  - topo sem seleção: no cabeçalho do datagrid;
 *  - topo com seleção: age nos registros marcados (um só, ou vários se "multiplos").
 */
class MdGerenciamentoBotao extends Model
{
    protected $connection = 'central';

    protected $table = 'md_gerenciamento_botoes';

    protected $fillable = [
        'md_gerenciamento_id', 'nome', 'titulo', 'nome_fisico', 'nome_arquivo', 'icone', 'cor',
        'no_registro', 'exige_selecao', 'multiplos', 'confirmar', 'ordem',
    ];

    protected function casts(): array
    {
        return [
            'no_registro' => 'boolean',
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
     * Assinatura do método na classe de botões, conforme onde o botão fica.
     */
    public function assinatura(string $model): string
    {
        return match (true) {
            $this->no_registro => "{$model} \$registro",
            $this->exige_selecao => 'Collection $registros',
            default => '',
        };
    }
}
