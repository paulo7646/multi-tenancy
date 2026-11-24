<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\AbreArquivosNoEditor;
use App\Filament\Resources\Dicionario\Concerns\CriaArquivosPorBotao;
use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Sistema\Geradores\Gerador;
use App\Sistema\Schema\SchemaException;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EditMdTabela extends EditRecord
{
    use AbreArquivosNoEditor;
    use CriaArquivosPorBotao;

    protected static string $resource = MdTabelaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            static::grupoDeArquivosDaTabela($this->getRecord()),
            $this->acaoCriarArquivos(),
            MdTabelaResource::acaoValores(),
            MdTabelaResource::acaoConstantes(),
            ViewAction::make(),
            MdTabelaResource::excluir(DeleteAction::make()),
        ];
    }

    protected function depoisDeSalvar(Model $record): void
    {
        // lista e conteúdo: NOME e VALOR (campos do sistema) aparecem logo no cadastro
        Gerador::garantirCamposDoSistema($record);
    }

    protected function previaDosArquivos(Model $record): array
    {
        return Gerador::previaTabela($record);
    }

    /**
     * Testa a migration que vai sair (no central ou em cada cliente, numa transação
     * desfeita). Se falhar, nada é gerado.
     */
    protected function verificarAntesDeCriar(Model $record): bool
    {
        try {
            Gerador::verificarTabelaSalva($record);

            return true;
        } catch (SchemaException $e) {
            Notification::make()
                ->title('A alteração não pode ser aplicada no banco')
                ->body(new HtmlString(collect($e->problems)
                    ->map(fn (array $lista, string $onde) => '<strong>'.e($onde).'</strong><br>'.collect($lista)->map(fn ($p) => '• '.e($p))->join('<br>'))
                    ->join('<br><br>').'<br><br>Nada foi gerado.'))
                ->danger()
                ->persistent()
                ->send();

            return false;
        }
    }

    protected function publicarNoDicionario(Model $record): array
    {
        return Gerador::publicarTabela($record);
    }
}
