<?php

namespace App\Plataforma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Uma versão do arquivo (cada envio novo gera outra; as antigas ficam guardadas).
 */
class Versao extends Model
{
    protected $table = 'doc_versoes';

    protected $fillable = [
        'arquivo_id', 'numero', 'disco', 'caminho', 'nome_original', 'mime', 'tamanho', 'hash', 'comentario', 'user_id', 'criado_por',
    ];

    public function arquivo(): BelongsTo
    {
        return $this->belongsTo(Arquivo::class, 'arquivo_id')->withTrashed();
    }

    public function conteudo(): ?string
    {
        $disco = Storage::disk($this->disco);

        return $disco->exists($this->caminho) ? $disco->get($this->caminho) : null;
    }

    /**
     * O conteúdo guardado ainda bate com o hash gravado no envio?
     */
    public function integra(): bool
    {
        $conteudo = $this->conteudo();

        return $conteudo !== null && hash_equals($this->hash, hash('sha256', $conteudo));
    }

    public function tamanhoLegivel(): string
    {
        $bytes = (float) $this->tamanho;

        foreach (['B', 'KB', 'MB', 'GB'] as $unidade) {
            if ($bytes < 1024 || $unidade === 'GB') {
                return ($unidade === 'B' ? (int) $bytes : number_format($bytes, 1, ',', '.')).' '.$unidade;
            }

            $bytes /= 1024;
        }

        return '';
    }
}
