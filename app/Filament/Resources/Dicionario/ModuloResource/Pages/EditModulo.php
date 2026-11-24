<?php

namespace App\Filament\Resources\Dicionario\ModuloResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\CriaArquivosPorBotao;
use App\Filament\Resources\Dicionario\ModuloResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use App\Sistema\Geradores\Gerador;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditModulo extends EditRecord
{
    use CriaArquivosPorBotao;
    use TituloPadrao;

    protected static string $resource = ModuloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->acaoCriarArquivos(),
            ModuloResource::excluir(DeleteAction::make()),
        ];
    }

    protected function previaDosArquivos(Model $record): array
    {
        return Gerador::previaModulo($record);
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarModulo($record);
    }
}
