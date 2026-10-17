<?php

namespace App\Filament\Resources\FormDefinitionResource\Pages\Concerns;

use App\Forms\FormGenerator;
use App\Forms\Schema\SchemaException;
use App\Models\FormDefinition;
use Filament\Notifications\Notification;
use Throwable;

/**
 * Antes de salvar: testa a migration que sairia do que está na tela
 * (bloqueia o salvamento se ela falharia). Depois de salvar: gera os arquivos.
 */
trait GeneratesForm
{
    /**
     * @param  FormDefinition  $form  formulário com os dados da tela (pode não estar salvo)
     */
    protected function checkForm(FormDefinition $form, array $data): void
    {
        try {
            FormGenerator::check($form, $this->data['fields'] ?? [], $data['roles'] ?? null);
        } catch (SchemaException $e) {
            Notification::make()
                ->title('Alteração bloqueada: a migration falharia')
                ->body($e->getMessage() . "\n\nNada foi salvo. Ajuste e tente de novo.")
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function generateForm(): void
    {
        try {
            $summary = FormGenerator::publish($this->record);

            Notification::make()
                ->title('Formulário gerado')
                ->body(implode("\n", $summary))
                ->success()
                ->persistent()
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('Formulário salvo, mas a geração falhou')
                ->body($e->getMessage() . "\n\nCorrija e salve de novo.")
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
