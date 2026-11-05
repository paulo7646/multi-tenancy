<?php

namespace App\Plataforma\Impressao;

use App\Models\Md\MdTabela;
use App\Plataforma\Models\ModeloImpressao;
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
            ->schema(fn () => static::escolhaDeModelo('registro', $tabela))
            ->action(function (Model $record, array $data) use ($tabela) {
                $modelo = static::modelo('registro', $tabela, $data['modelo'] ?? null);
                $pdf = Relatorios::registro($tabela, $record, $modelo);

                return response()->streamDownload(fn () => print $pdf, Str::slug("{$tabela->nome}-{$record->getKey()}").'.pdf', ['Content-Type' => 'application/pdf']);
            });
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

                ...static::escolhaDeModelo('lista', $tabela, visivelSe: 'pdf'),
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
                    $pdf = Relatorios::lista($tabela, $consulta->get(), static::modelo('lista', $tabela, $data['modelo'] ?? null));

                    return response()->streamDownload(fn () => print $pdf, "{$nome}.pdf", ['Content-Type' => 'application/pdf']);
                }

                $arquivo = Planilhas::gerar($tabela, $consulta, $formato);

                return response()->download($arquivo, "{$nome}.{$formato}")->deleteFileAfterSend();
            });
    }

    /**
     * Campo para escolher o modelo, quando há mais de um para a tabela.
     *
     * @return list<Select>
     */
    protected static function escolhaDeModelo(string $tipo, MdTabela $tabela, ?string $visivelSe = null): array
    {
        $modelos = ModeloImpressao::daTabela($tipo, $tabela->nome_fisico)->orderBy('nome')->pluck('nome', 'id');

        if ($modelos->count() < 2) {
            return [];
        }

        $padrao = ModeloImpressao::padraoDa($tipo, $tabela->nome_fisico);

        return [
            Select::make('modelo')
                ->label('Modelo de impressão')
                ->options($modelos->all())
                ->default($padrao?->getKey() ?? $modelos->keys()->first())
                ->native(false)
                ->visible(fn ($get) => $visivelSe === null || $get('formato') === $visivelSe),
        ];
    }

    protected static function modelo(string $tipo, MdTabela $tabela, mixed $escolhido): ?ModeloImpressao
    {
        if (filled($escolhido)) {
            return ModeloImpressao::daTabela($tipo, $tabela->nome_fisico)->find($escolhido);
        }

        return ModeloImpressao::padraoDa($tipo, $tabela->nome_fisico)
            ?? ModeloImpressao::daTabela($tipo, $tabela->nome_fisico)->orderBy('id')->first();
    }
}
