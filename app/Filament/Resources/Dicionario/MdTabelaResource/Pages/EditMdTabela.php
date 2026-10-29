<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\Concerns\VerificaMigration;
use App\Sistema\Geradores\Gerador;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMdTabela extends EditRecord
{
    use PublicaNoDicionario;
    use VerificaMigration;

    protected static string $resource = MdTabelaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdTabelaResource::acaoValores(),
            MdTabelaResource::acaoConstantes(),
            ViewAction::make(),
            MdTabelaResource::excluir(DeleteAction::make()),
        ];
    }

    protected function beforeSave(): void
    {
        // cópia: o teste não pode mexer no registro que o Filament vai gravar
        $this->verificarMigration(clone $this->record);
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarTabela($record);
    }
}
