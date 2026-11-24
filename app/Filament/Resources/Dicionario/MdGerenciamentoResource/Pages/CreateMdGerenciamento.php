<?php

namespace App\Filament\Resources\Dicionario\MdGerenciamentoResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\SalvaNoDicionario;
use App\Filament\Resources\Dicionario\MdGerenciamentoResource;
use App\Models\Md\MdTabela;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Resources\Pages\CreateRecord;

class CreateMdGerenciamento extends CreateRecord
{
    use SalvaNoDicionario;
    use TituloPadrao;

    protected static string $resource = MdGerenciamentoResource::class;

    /**
     * Vindo da aba da tabela (?tabela=id): já começa com a tabela e as sugestões.
     */
    protected function afterFill(): void
    {
        if ($tabela = MdTabela::with('modulo')->find(request()->query('tabela'))) {
            $this->form->fill([...$this->form->getRawState(), 'md_tabela_id' => $tabela->id, ...MdGerenciamentoResource::sugestoes($tabela)]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
