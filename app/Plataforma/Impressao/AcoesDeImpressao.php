<?php

namespace App\Plataforma\Impressao;

use App\Models\Md\MdTabela;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Botões de impressão das telas do dicionário: Imprimir (um registro, em PDF) e
 * Exportar (a lista com os filtros atuais, em PDF, Excel ou CSV).
 */
class AcoesDeImpressao
{
    /** Limite de linhas por formato (PDF é bem mais pesado) */
    public const MAX_PDF = 2000;

    public const MAX_PLANILHA = 100000;

    public static function registro(MdTabela $tabela): Action
    {
        return Action::make('imprimir')
            ->label('Imprimir')
            ->icon('heroicon-o-printer')
            ->iconButton()
            ->color('gray')
            ->action(fn (Model $record) => static::baixarRegistro($tabela, $record));
    }

    /**
     * PDF de um registro (a ação Imprimir do topo do datagrid, com um registro selecionado).
     */
    public static function baixarRegistro(MdTabela $tabela, Model $record): mixed
    {
        $pdf = Relatorios::registro($tabela, $record);

        return response()->streamDownload(fn () => print $pdf, Str::slug("{$tabela->nome}-{$record->getKey()}").'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public static function exportar(MdTabela $tabela): Action
    {
        return Action::make('exportar')
            ->label('Exportar')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->modalHeading("Exportar {$tabela->nome}")
            ->modalDescription('Exporta os registros da lista com os filtros e a busca atuais.')
            ->modalSubmitActionLabel('Exportar')
            ->schema([
                Select::make('formato')
                    ->label('Formato')
                    ->options(['pdf' => 'PDF', 'xlsx' => 'Excel (xlsx)', 'csv' => 'CSV'])
                    ->default('xlsx')
                    ->required()
                    ->live()
                    ->native(false),
            ])
            ->action(function (array $data, $livewire) use ($tabela) {
                $consulta = $livewire->getFilteredSortedTableQuery();
                $formato = $data['formato'];
                $nome = Str::slug($tabela->nome).'-'.now()->format('Ymd-His');
                $limite = $formato === 'pdf' ? static::MAX_PDF : static::MAX_PLANILHA;

                if ($consulta->count() > $limite) {
                    Notification::make()->title('Muitos registros')
                        ->body("O {$formato} aceita até ".number_format($limite, 0, ',', '.').' linhas. Use os filtros para reduzir a lista.')
                        ->danger()->send();

                    return null;
                }

                if ($formato === 'pdf') {
                    $pdf = Relatorios::lista($tabela, $consulta->get());

                    return response()->streamDownload(fn () => print $pdf, "{$nome}.pdf", ['Content-Type' => 'application/pdf']);
                }

                $arquivo = Planilhas::gerar($tabela, $consulta, $formato);

                return response()->download($arquivo, "{$nome}.{$formato}")->deleteFileAfterSend();
            });
    }
}
