<?php

namespace App\Plataforma\Resources;

use App\Plataforma\Assinatura\AssinaturaException;
use App\Plataforma\Documentos\Armazenamento;
use App\Plataforma\Documentos\DocumentoException;
use App\Plataforma\Models\Arquivo;
use App\Plataforma\Models\Pasta;
use App\Plataforma\RelationManagers\VersoesRelationManager;
use App\Plataforma\Resources\DocumentoResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Gestão de documentos: pastas, versões, tags, lixeira e envio para assinatura.
 */
class DocumentoResource extends Resource
{
    protected static ?string $model = Arquivo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentos';

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    protected static ?string $slug = 'documentos';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nome')->required()->maxLength(200),
            Select::make('pasta_id')->label('Pasta')->options(fn () => Pasta::opcoes())->searchable()->native(false),
            Textarea::make('descricao')->label('Descrição')->rows(3)->columnSpanFull(),
            TagsInput::make('tags')->columnSpanFull(),
        ]);
    }

    /**
     * Campos do envio de um documento novo (também usado no botão da lista).
     *
     * @return list<Component>
     */
    public static function camposDeEnvio(): array
    {
        return [
            FileUpload::make('arquivo')
                ->label('Arquivo')
                ->required()
                ->storeFiles(false)
                ->maxSize((int) config('plataforma.documentos.max_mb') * 1024)
                ->columnSpanFull(),
            TextInput::make('nome')->label('Nome do documento')->maxLength(200)->helperText('Em branco, usa o nome do arquivo.'),
            Select::make('pasta_id')->label('Pasta')->options(fn () => Pasta::opcoes())->searchable()->native(false),
            TagsInput::make('tags')->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function enviar(array $dados): ?Arquivo
    {
        try {
            $upload = $dados['arquivo'];

            return Armazenamento::criar(
                ['nome' => $dados['nome'] ?: null, 'pasta_id' => $dados['pasta_id'] ?? null, 'tags' => $dados['tags'] ?? null],
                Armazenamento::deUpload($upload instanceof TemporaryUploadedFile ? $upload : $upload),
            );
        } catch (DocumentoException $e) {
            Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->danger()->send();

            return null;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('nome')->searchable()->sortable()->weight('bold'),
                TextColumn::make('pasta.nome')->label('Pasta')->placeholder('—')->sortable(),
                TextColumn::make('versaoAtual.nome_original')->label('Arquivo')->limit(30),
                TextColumn::make('versaoAtual.tamanho')->label('Tamanho')->formatStateUsing(fn (Arquivo $record) => $record->versaoAtual?->tamanhoLegivel()),
                TextColumn::make('versaoAtual.numero')->label('Versão')->prefix('v'),
                TextColumn::make('tags')->badge()->separator(',')->toggleable(),
                TextColumn::make('criado_por')->label('Enviado por')->toggleable(),
                TextColumn::make('updated_at')->label('Atualizado')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('pasta_id')->label('Pasta')->options(fn () => Pasta::opcoes()),
                SelectFilter::make('tipo')->label('Tipo')
                    ->options(['application/pdf' => 'PDF', 'image' => 'Imagem', 'text' => 'Texto', 'application' => 'Outros'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $tipo) => $q->whereHas('versaoAtual', fn ($v) => $v->where('mime', 'like', $tipo === 'application' ? 'application/%' : "{$tipo}%")))),
                TrashedFilter::make()->label('Lixeira'),
            ])
            ->recordActions([
                Action::make('baixar')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->visible(fn (Arquivo $record) => ! $record->trashed() && $record->versaoAtual !== null)
                    ->action(function (Arquivo $record) {
                        $versao = $record->versaoAtual;

                        return response()->streamDownload(fn () => print $versao->conteudo(), $versao->nome_original, ['Content-Type' => $versao->mime ?: 'application/octet-stream']);
                    }),

                Action::make('nova_versao')
                    ->label('Nova versão')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn (Arquivo $record) => ! $record->trashed())
                    ->schema([
                        FileUpload::make('arquivo')->label('Arquivo')->required()->storeFiles(false)->maxSize((int) config('plataforma.documentos.max_mb') * 1024),
                        TextInput::make('comentario')->label('O que mudou')->maxLength(255),
                    ])
                    ->action(function (Arquivo $record, array $data) {
                        try {
                            Armazenamento::novaVersao($record, Armazenamento::deUpload($data['arquivo']), $data['comentario'] ?? null);
                            Notification::make()->title('Versão enviada')->success()->send();
                        } catch (DocumentoException $e) {
                            Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('assinar')
                    ->label('Enviar para assinatura')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Arquivo $record) => ! $record->trashed() && $record->ehPdf())
                    ->modalHeading(fn (Arquivo $record) => "Assinatura: {$record->nome}")
                    ->modalWidth('3xl')
                    ->fillForm(fn (Arquivo $record) => ['titulo' => $record->nome])
                    ->schema(AssinaturaResource::camposDoPedido(comDocumento: false))
                    ->action(function (Arquivo $record, array $data) {
                        try {
                            $pedido = AssinaturaResource::criarPedido($data, $record->versaoAtual);

                            Notification::make()->title($pedido->status === 'em_andamento' ? 'Pedido enviado para assinatura' : 'Pedido salvo como rascunho')
                                ->actions([Action::make('abrir')->label('Abrir')->url(AssinaturaResource::getUrl('view', ['record' => $pedido]))])
                                ->success()->send();
                        } catch (AssinaturaException $e) {
                            Notification::make()->title('Não foi possível criar o pedido')->body($e->getMessage())->danger()->send();
                        }
                    }),

                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()->modalDescription('O documento vai para a lixeira e pode ser restaurado.'),
                RestoreAction::make()->iconButton(),
                ForceDeleteAction::make()->iconButton()
                    ->using(function (Arquivo $record) {
                        Armazenamento::apagarDefinitivamente($record);

                        return true;
                    })
                    ->modalDescription('Apaga o documento e todas as versões. Não dá para desfazer.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [VersoesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentos::route('/'),
            'edit' => Pages\EditDocumento::route('/{record}/edit'),
        ];
    }
}
