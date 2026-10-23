<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Componente de um formulário: um campo (com o tipo de componente, a
 * tradução da FK e os parâmetros) ou um grupo, que tem componentes dentro.
 */
class MdFormularioComponente extends Model
{
    protected $connection = 'central';

    protected $table = 'md_formulario_componentes';

    protected $fillable = [
        'md_formulario_id', 'md_formulario_pagina_id', 'pai_id', 'pagina', 'ordem', 'tipo', 'campo', 'componente', 'mascara', 'traducao_id', 'titulo',
        'colunas', 'colunas_grupo', 'quebra', 'completar', 'obrigatorio', 'largura', 'altura',
    ];

    protected function casts(): array
    {
        return [
            'pagina' => 'integer',
            'ordem' => 'integer',
            'colunas' => 'integer',
            'colunas_grupo' => 'integer',
            'quebra' => 'boolean',
            'completar' => 'boolean',
            'obrigatorio' => 'boolean',
            'largura' => 'integer',
            'altura' => 'integer',
        ];
    }

    public function formulario(): BelongsTo
    {
        return $this->belongsTo(MdFormulario::class, 'md_formulario_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(static::class, 'pai_id')->orderBy('ordem')->orderBy('id');
    }

    public function traducao(): BelongsTo
    {
        return $this->belongsTo(MdTabelaTraducao::class, 'traducao_id');
    }
}
