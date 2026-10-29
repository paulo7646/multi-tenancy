<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Contexto;
use App\Webservice\Models\Busca;
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
 * Campos de busca: localizam o registro na tabela do evento (campo operador valor).
 */
class BuscaRelationManager extends RelationManager
{
    protected static string $relationship = 'busca';

    protected static ?string $title = 'Campos de busca';

    protected static ?string $modelLabel = 'condição';

    protected static ?string $pluralModelLabel = 'condições';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('campo')
                    ->options(fn () => Contexto::campos($this->getOwnerRecord()->tabela))
                    ->required()
                    ->searchable()
                    ->native(false),
                Select::make('operador')->options(Busca::OPERADORES)->default('=')->required()->native(false),
                TextInput::make('valor')->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campo'),
                TextColumn::make('operador')->formatStateUsing(fn (?string $state) => Busca::OPERADORES[$state] ?? $state),
                TextColumn::make('valor'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
