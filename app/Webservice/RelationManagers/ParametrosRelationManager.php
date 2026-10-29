<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Models\Parametro;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParametrosRelationManager extends RelationManager
{
    protected static string $relationship = 'parametros';

    protected static ?string $title = 'Parâmetros';

    protected static ?string $modelLabel = 'parâmetro';

    protected static ?string $pluralModelLabel = 'parâmetros';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nome')->required()->maxLength(100),
                Select::make('local')
                    ->label('Enviado')
                    ->options(Parametro::LOCAIS)
                    ->default('query')
                    ->required()
                    ->native(false),
                TextInput::make('valor')->helperText('Valor fixo; em branco, o valor é informado na execução.')->columnSpanFull(),
                Toggle::make('obrigatorio')->label('Obrigatório'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('local')->label('Enviado')->badge()->formatStateUsing(fn (?string $state) => match ($state) {
                    'url' => 'URL',
                    'corpo' => 'Corpo',
                    'header' => 'Header',
                    default => 'Query',
                }),
                TextColumn::make('valor')->limit(40)->placeholder('na execução'),
                IconColumn::make('obrigatorio')->label('Obrig.')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
