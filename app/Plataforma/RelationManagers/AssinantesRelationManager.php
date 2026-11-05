<?php

namespace App\Plataforma\RelationManagers;

use App\Plataforma\Assinatura\Assinaturas;
use App\Plataforma\Models\Assinante;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Quem deve assinar, a situação de cada um e o reenvio do convite.
 */
class AssinantesRelationManager extends RelationManager
{
    protected static string $relationship = 'assinantes';

    protected static ?string $title = 'Assinantes';

    protected static ?string $modelLabel = 'assinante';

    protected static ?string $pluralModelLabel = 'assinantes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('ordem')->label('#'),
                TextColumn::make('nome')->description(fn (Assinante $record) => $record->email),
                TextColumn::make('papel')->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'assinou' => 'success', 'recusou' => 'danger', default => 'warning',
                    })
                    ->formatStateUsing(fn (?string $state) => Assinante::STATUS[$state] ?? $state),
                TextColumn::make('notificado_em')->label('Convidado')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('assinado_em')->label('Assinou em')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('ip')->label('IP')->placeholder('—')->toggleable(),
            ])
            ->recordActions([
                Action::make('reenviar')
                    ->label('Reenviar convite')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (Assinante $record) => $record->status === 'pendente' && $this->getOwnerRecord()->status === 'em_andamento')
                    ->requiresConfirmation()
                    ->modalDescription('Gera um link novo; o anterior deixa de valer.')
                    ->action(function (Assinante $record) {
                        Assinaturas::convidar($record);
                        Notification::make()->title("Convite enviado para {$record->email}")->success()->send();
                    }),
            ]);
    }
}
