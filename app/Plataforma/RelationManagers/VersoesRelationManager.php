<?php

namespace App\Plataforma\RelationManagers;

use App\Plataforma\Models\Versao;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Histórico de versões de um documento: baixar qualquer uma ou voltar para ela.
 */
class VersoesRelationManager extends RelationManager
{
    protected static string $relationship = 'versoes';

    protected static ?string $title = 'Versões';

    protected static ?string $modelLabel = 'versão';

    protected static ?string $pluralModelLabel = 'versões';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('Versão')->prefix('v'),
                IconColumn::make('atual')->label('Atual')->boolean()->state(fn (Versao $record) => $record->getKey() === $this->getOwnerRecord()->versao_atual_id),
                TextColumn::make('nome_original')->label('Arquivo'),
                TextColumn::make('tamanho')->formatStateUsing(fn (Versao $record) => $record->tamanhoLegivel()),
                TextColumn::make('comentario')->placeholder('—'),
                TextColumn::make('hash')->label('SHA-256')->limit(14)->tooltip(fn (Versao $record) => $record->hash)->toggleable(),
                TextColumn::make('criado_por')->label('Enviado por'),
                TextColumn::make('created_at')->label('Em')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('baixar')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->iconButton()
                    ->action(fn (Versao $record) => response()->streamDownload(fn () => print $record->conteudo(), $record->nome_original, ['Content-Type' => $record->mime ?: 'application/octet-stream'])),

                Action::make('restaurar')
                    ->label('Tornar atual')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Versao $record) => $record->getKey() !== $this->getOwnerRecord()->versao_atual_id && auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->action(function (Versao $record) {
                        $this->getOwnerRecord()->update(['versao_atual_id' => $record->getKey()]);
                        Notification::make()->title("v{$record->numero} é a versão atual")->success()->send();
                    }),
            ]);
    }
}
