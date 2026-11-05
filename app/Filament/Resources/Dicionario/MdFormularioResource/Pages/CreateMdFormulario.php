<?php

namespace App\Filament\Resources\Dicionario\MdFormularioResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\SalvaNoDicionario;
use App\Filament\Resources\Dicionario\MdFormularioResource;
use App\Filament\Resources\Dicionario\MdFormularioResource\Pages\Concerns\DesenhaPrevia;
use App\Models\Md\MdTabela;
use Filament\Resources\Pages\CreateRecord;

class CreateMdFormulario extends CreateRecord
{
    use DesenhaPrevia;
    use SalvaNoDicionario;

    protected static string $resource = MdFormularioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdFormularioResource::previa(),
        ];
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
