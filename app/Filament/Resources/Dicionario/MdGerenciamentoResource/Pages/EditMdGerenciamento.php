<?php

namespace App\Filament\Resources\Dicionario\MdGerenciamentoResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\CriaArquivosPorBotao;
use App\Filament\Resources\Dicionario\MdGerenciamentoResource;
use App\Sistema\Geradores\Gerador;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMdGerenciamento extends EditRecord
{
    use CriaArquivosPorBotao;

    protected static string $resource = MdGerenciamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->acaoCriarArquivos(),
            MdGerenciamentoResource::acaoAbrir(),
            MdGerenciamentoResource::excluir(DeleteAction::make()),
        ];
    }

    protected function motivoParaNaoCriarArquivos(Model $record): ?string
    {
        return $record->tabela?->isGerada() ? null : "Crie primeiro os arquivos da tabela \"{$record->tabela?->nome}\".";
    }

    protected function previaDosArquivos(Model $record): array
    {
        return Gerador::previaGerenciamento($record);
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarGerenciamento($record);
    }
}
