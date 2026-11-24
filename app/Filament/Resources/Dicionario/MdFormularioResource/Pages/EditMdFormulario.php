<?php

namespace App\Filament\Resources\Dicionario\MdFormularioResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\AbreArquivosNoEditor;
use App\Filament\Resources\Dicionario\Concerns\CriaArquivosPorBotao;
use App\Filament\Resources\Dicionario\MdFormularioResource;
use App\Filament\Resources\Dicionario\MdFormularioResource\Pages\Concerns\DesenhaPrevia;
use App\Sistema\Base\Concerns\TituloPadrao;
use App\Sistema\Geradores\Gerador;
use App\Sistema\Runtime\Layout;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMdFormulario extends EditRecord
{
    use AbreArquivosNoEditor;
    use CriaArquivosPorBotao;
    use DesenhaPrevia;
    use TituloPadrao;

    protected static string $resource = MdFormularioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::acaoAbrirArquivoDoFormulario($this->getRecord()),
            $this->acaoCriarArquivos(),
            MdFormularioResource::previa(),
            MdFormularioResource::excluir(DeleteAction::make()),
        ];
    }

    protected function depoisDeSalvar(Model $record): void
    {
        // os campos já saíram dos grupos ao gravar a aba Componentes (grupos ficam para depois)
        $record->componentes()->where('tipo', Layout::GRUPO)->delete();
    }

    protected function motivoParaNaoCriarArquivos(Model $record): ?string
    {
        return $record->tabela?->isGerada() ? null : "Crie primeiro os arquivos da tabela \"{$record->tabela?->nome}\".";
    }

    protected function previaDosArquivos(Model $record): array
    {
        return Gerador::previaFormulario($record);
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarFormulario($record);
    }
}
