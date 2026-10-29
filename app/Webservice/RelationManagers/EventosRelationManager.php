<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Models\Evento;
use App\Webservice\Resources\EventoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventosRelationManager extends RelationManager
{
    protected static string $relationship = 'eventos';

    protected static ?string $title = 'Eventos';

    protected static ?string $modelLabel = 'evento';

    protected static ?string $pluralModelLabel = 'eventos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(EventoResource::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('tipo')->badge()->formatStateUsing(fn (?string $state) => Evento::TIPOS[$state] ?? $state),
                TextColumn::make('tabela'),
                TextColumn::make('funcao')->label('Função')->placeholder('—'),
                TextColumn::make('ordem'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('abrir')
                    ->label('Busca e atributos')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Evento $record) => EventoResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
