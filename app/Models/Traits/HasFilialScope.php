<?php

namespace App\Models\Traits;

use App\Models\Scopes\FilialScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Filial;

trait HasFilialScope
{
    /**
     * Inicializa a trait, registrando o escopo global e ouvindo eventos do Eloquent.
     */
    protected static function bootHasFilialScope(): void
    {
        // 1. Aplica o escopo global para filtrar registros da filial ativa
        static::addGlobalScope(new FilialScope);

        // 2. Preenche automaticamente a filial ao criar um novo registro
        static::creating(function ($model) {
            // Verifica se há um usuário autenticado no contexto atual (evita erros no CLI/Jobs)
            if (auth()->hasUser()) {
                $filialId = auth()->user()->getActiveFilialId();
                
                // Aplica a filial ativa se o campo filial_id estiver vazio (null)
                // Isso previne que um admin de filial crie registros "órfãos" e tome erro 404
                if ($filialId && empty($model->filial_id)) {
                    $model->filial_id = $filialId;
                }
            }
        });
    }

    /**
     * Define o relacionamento padrão com a Filial para todas as models que usarem esta trait.
     */
    public function filial(): BelongsTo
    {
        return $this->belongsTo(Filial::class, 'filial_id');
    }
}
