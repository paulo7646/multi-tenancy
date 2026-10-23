<?php

namespace App\Models\Md;

use App\Sistema\Enums\TipoTabPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tab page de um formulário (ver a migration para os tipos).
 */
class MdFormularioPagina extends Model
{
    protected $connection = 'central';

    protected $table = 'md_formulario_paginas';

    /** Ligação pelo id do registro (campo_tabela / campo_ligacao) */
    public const CAMPO_ID = '__id';

    protected $fillable = [
        'md_formulario_id', 'nome', 'nome_fisico', 'tipo', 'ordem', 'fk_campo', 'tabela_id', 'campo_tabela', 'campo_ligacao', 'sem_permissao',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTabPage::class,
            'ordem' => 'integer',
            'sem_permissao' => 'boolean',
        ];
    }

    public function formulario(): BelongsTo
    {
        return $this->belongsTo(MdFormulario::class, 'md_formulario_id');
    }

    public function componentes(): HasMany
    {
        return $this->hasMany(MdFormularioComponente::class, 'md_formulario_pagina_id')->orderBy('ordem')->orderBy('id');
    }

    /** Tipo Tabela: a tabela de destino */
    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'tabela_id');
    }

    /**
     * Método do Resource para o tipo Customizada: tabpage{NomeFisico}() (ex.: tabpageEstoque).
     */
    public static function metodoCustomizado(string $nomeFisico): string
    {
        return 'tabpage' . Str::studly(Str::lower($nomeFisico));
    }
}
