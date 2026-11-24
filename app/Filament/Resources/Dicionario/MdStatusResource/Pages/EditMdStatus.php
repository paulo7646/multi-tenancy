<?php

namespace App\Filament\Resources\Dicionario\MdStatusResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdStatusResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use App\Sistema\Geradores\Gerador;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMdStatus extends EditRecord
{
    use PublicaNoDicionario;
    use TituloPadrao;

    protected static string $resource = MdStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MdStatusResource::excluir(DeleteAction::make()),
        ];
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarStatus();
    }
}
