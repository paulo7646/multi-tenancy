<?php

namespace App\Plataforma\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Trilha de auditoria do pedido (só leitura; cada evento carrega o hash do anterior).
 */
class EventosDaAssinaturaRelationManager extends RelationManager
{
    protected static string $relationship = 'eventos';

    protected static ?string $title = 'Trilha de auditoria';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s'),
                TextColumn::make('descricao')->label('Evento')->wrap(),
                TextColumn::make('ip')->label('IP')->placeholder('—'),
                TextColumn::make('hash')->label('Hash')->limit(12)->tooltip(fn ($record) => $record->hash)->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
