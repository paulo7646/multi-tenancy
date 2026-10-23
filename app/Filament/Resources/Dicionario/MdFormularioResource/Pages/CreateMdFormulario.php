<?php

namespace App\Filament\Resources\Dicionario\MdFormularioResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdFormularioResource\Pages\Concerns\DesenhaPrevia;
use App\Filament\Resources\Dicionario\MdFormularioResource;
use App\Models\Md\MdTabela;
use App\Sistema\Geradores\Gerador;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMdFormulario extends CreateRecord
{
    use DesenhaPrevia;
    use PublicaNoDicionario;

    protected static string $resource = MdFormularioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdFormularioResource::previa(),
        ];
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarFormulario($record);
    }

    /**
     * Vindo da aba da tabela (?tabela=id): já começa com a tabela e as sugestões.
     */
    protected function afterFill(): void
    {
        if ($tabela = MdTabela::with('modulo')->find(request()->query('tabela'))) {
            $this->form->fill([...$this->form->getRawState(), 'md_tabela_id' => $tabela->id, ...MdFormularioResource::sugestoes($tabela)]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
