<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Contexto;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Atributos do retorno que viram colunas da tabela do evento.
 */
class AtributosRelationManager extends RelationManager
{
    protected static string $relationship = 'atributos';

    protected static ?string $title = 'Atributos';

    protected static ?string $modelLabel = 'atributo';

    protected static ?string $pluralModelLabel = 'atributos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nome_json')->label('Atributo no retorno')->required()->maxLength(100),
                Select::make('campo')
                    ->label('Campo da tabela')
                    ->options(fn () => Contexto::campos($this->getOwnerRecord()->tabela))
                    ->required()
                    ->searchable()
                    ->native(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome_json')->label('Atributo no retorno'),
                TextColumn::make('campo')->label('Campo da tabela'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
