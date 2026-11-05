<?php

namespace App\Plataforma\RelationManagers;

use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Documentos\DocumentoException;
use App\Plataforma\Models\Anexo;
use App\Plataforma\Models\Arquivo;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Documentos anexados a um registro de qualquer tabela do dicionário. O anexo é
 * um documento da gestão de arquivos (com versões), ligado ao registro.
 */
class AnexosRelationManager extends RelationManager
{
    protected static string $relationship = 'anexos';

    protected static ?string $title = 'Anexos';

    protected static ?string $modelLabel = 'anexo';

    protected static ?string $pluralModelLabel = 'anexos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('arquivo.nome')->label('Documento')->searchable(),
                TextColumn::make('arquivo.versaoAtual.nome_original')->label('Arquivo'),
                TextColumn::make('arquivo.versaoAtual.tamanho')->label('Tamanho')
                    ->formatStateUsing(fn (Anexo $record) => $record->arquivo->versaoAtual?->tamanhoLegivel()),
                TextColumn::make('arquivo.versaoAtual.numero')->label('Versão')->prefix('v'),
                TextColumn::make('created_at')->label('Anexado em')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('enviar')
                    ->label('Enviar arquivo')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        FileUpload::make('arquivo')->label('Arquivo')->required()->storeFiles(false)->maxSize((int) config('plataforma.documentos.max_mb') * 1024),
                        TextInput::make('nome')->label('Nome do documento')->maxLength(200)->helperText('Em branco, usa o nome do arquivo.'),
                    ])
                    ->action(function (array $data) {
                        try {
                            $upload = $data['arquivo'];
                            $arquivo = Armazenamento::criar(
                                ['nome' => $data['nome'] ?: null],
                                Armazenamento::deUpload($upload instanceof TemporaryUploadedFile ? $upload : $upload),
                            );

                            $this->vincular($arquivo);
                            Notification::make()->title('Arquivo anexado')->success()->send();
                        } catch (DocumentoException $e) {
                            Notification::make()->title('Não foi possível anexar')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('vincular')
                    ->label('Vincular documento existente')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->schema([
                        Select::make('arquivo_id')
                            ->label('Documento')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(fn (string $busca) => Arquivo::where('nome', 'ilike', "%{$busca}%")->limit(30)->pluck('nome', 'id')->all())
                            ->getOptionLabelUsing(fn ($valor) => Arquivo::find($valor)?->nome),
                    ])
                    ->action(function (array $data) {
                        $this->vincular(Arquivo::findOrFail($data['arquivo_id']));
                        Notification::make()->title('Documento vinculado')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('baixar')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->visible(fn (Anexo $record) => $record->arquivo?->versaoAtual !== null)
                    ->action(function (Anexo $record) {
                        $versao = $record->arquivo->versaoAtual;

                        return response()->streamDownload(fn () => print $versao->conteudo(), $versao->nome_original, ['Content-Type' => $versao->mime ?: 'application/octet-stream']);
                    }),

                DeleteAction::make()->label('Desvincular')->modalHeading('Desvincular documento')
                    ->modalDescription('O documento continua na gestão de arquivos; só deixa de estar ligado a este registro.'),
            ]);
    }

    protected function vincular(Arquivo $arquivo): void
    {
        $this->getRelationship()->firstOrCreate(['arquivo_id' => $arquivo->getKey()], ['user_id' => auth()->id()]);
    }
}
