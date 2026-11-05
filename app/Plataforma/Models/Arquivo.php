<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento: o nome e os dados; o conteúdo está nas versões. Apagar manda para a lixeira.
 */
class Arquivo extends Model
{
    use SoftDeletes;

    protected $table = 'doc_arquivos';

    protected $fillable = ['pasta_id', 'nome', 'descricao', 'tags', 'versao_atual_id', 'user_id', 'criado_por'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function pasta(): BelongsTo
    {
        return $this->belongsTo(Pasta::class, 'pasta_id');
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(Versao::class, 'arquivo_id')->orderByDesc('numero');
    }

    public function versaoAtual(): BelongsTo
    {
        return $this->belongsTo(Versao::class, 'versao_atual_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(Anexo::class, 'arquivo_id');
    }

    public function ehPdf(): bool
    {
        return $this->versaoAtual?->mime === 'application/pdf';
    }
}
