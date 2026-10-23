<?php

namespace App\Filament\Resources\Dicionario\MdGerenciamentoResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdGerenciamentoResource;
use App\Sistema\Geradores\Gerador;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMdGerenciamento extends EditRecord
{
    use PublicaNoDicionario;

    protected static string $resource = MdGerenciamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdGerenciamentoResource::acaoAbrir(),
            MdGerenciamentoResource::excluir(DeleteAction::make()),
        ];
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarGerenciamento($record);
    }
}
