<?php

namespace App\Filament\Resources\Dicionario\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Páginas de criar/editar do dicionário cujos arquivos saem no botão Criar
 * arquivos: salvar só grava o cadastro (e o que for do banco do dicionário).
 */
trait SalvaNoDicionario
{
    /**
     * Depois de gravar o cadastro. Só o que fica no banco do dicionário (nunca arquivos).
     */
    protected function depoisDeSalvar(Model $record): void {}

    protected function afterCreate(): void
    {
        $this->depoisDeSalvar($this->record);
    }

    protected function afterSave(): void
    {
        $this->depoisDeSalvar($this->record);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Salvo')
            ->body('Os arquivos só são gerados no botão Criar arquivos.')
            ->success();
    }
}
