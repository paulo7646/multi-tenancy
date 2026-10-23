<?php

namespace App\Models\Md;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MdFormularioBotao extends Model
{
    protected $connection = 'central';

    protected $table = 'md_formulario_botoes';

    protected $fillable = ['md_formulario_id', 'nome', 'titulo', 'nome_fisico', 'nome_arquivo', 'icone', 'cor', 'confirmar', 'ordem'];

    protected function casts(): array
    {
        return ['confirmar' => 'boolean', 'ordem' => 'integer'];
    }

    public function formulario(): BelongsTo
    {
        return $this->belongsTo(MdFormulario::class, 'md_formulario_id');
    }
}
