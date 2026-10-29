<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Models\Objeto;
use App\Webservice\Resources\ObjetoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ObjetosRelationManager extends RelationManager
{
    protected static string $relationship = 'objetos';

    protected static ?string $title = 'Objetos';

    protected static ?string $modelLabel = 'objeto';

    protected static ?string $pluralModelLabel = 'objetos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(ObjetoResource::campos($this->getOwnerRecord()->getKey()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('pai.nome')->label('Pai')->placeholder('raiz'),
                IconColumn::make('lista')->boolean(),
                IconColumn::make('obrigatorio')->label('Obrig.')->boolean(),
                IconColumn::make('novo_lote')->label('Novo lote')->boolean(),
                TextColumn::make('eventos_count')->counts('eventos')->label('Eventos'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('eventos')
                    ->label('Eventos')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Objeto $record) => ObjetoResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
