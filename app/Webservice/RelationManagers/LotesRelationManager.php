<?php

namespace App\Webservice\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lotes da integração: cada um é uma transação; o que deu erro foi desfeito.
 */
class LotesRelationManager extends RelationManager
{
    protected static string $relationship = 'lotes';

    protected static ?string $title = 'Lotes';

    protected static ?string $modelLabel = 'lote';

    protected static ?string $pluralModelLabel = 'lotes';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('Lote'),
                TextColumn::make('objeto.nome')->label('Objeto')->placeholder('integração inteira'),
                TextColumn::make('status')->badge()
                    ->color(fn (?string $state) => $state === 'ok' ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state) => $state === 'ok' ? 'OK' : 'Erro (desfeito)'),
                TextColumn::make('mensagem')->wrap()->placeholder('—'),
            ]);
    }
}
