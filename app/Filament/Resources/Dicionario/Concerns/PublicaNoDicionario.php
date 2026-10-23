<?php

namespace App\Filament\Resources\Dicionario\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Páginas de criar/editar do dicionário: depois de gravar o cadastro, gera
 * os arquivos (migration, model, classes) e mostra o resumo.
 */
trait PublicaNoDicionario
{
    /**
     * @return list<string> resumo do que foi gerado
     */
    abstract protected function publicarNoDicionario(Model $record): array;

    protected function afterCreate(): void
    {
        $this->publicar();
    }

    protected function afterSave(): void
    {
        $this->publicar();
    }

    /**
     * Antes de gerar os arquivos (ex.: gravar tabelas filhas que não são campos do formulário).
     */
    protected function antesDePublicar(Model $record): void {}

    protected function publicar(): void
    {
        $this->antesDePublicar($this->record);

        try {
            $resumo = $this->publicarNoDicionario($this->record);

            if ($resumo !== []) {
                Notification::make()
                    ->title('Arquivos gerados')
                    ->body(new HtmlString(implode('<br>', array_map('e', $resumo))))
                    ->success()
                    ->send();
            }
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('O cadastro foi salvo, mas a geração falhou')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Salvo')->success();
    }
}
