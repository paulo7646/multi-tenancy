<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\FilialResource\Pages;
use App\Models\Filial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class FilialResource extends BaseResource
{
    protected static ?string $model = Filial::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Filiais';

    protected static ?string $navigationGroup = 'Configurações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descricao')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('ativo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('descricao')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('ativo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Usuários')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('ativo'),
            ])
            ->actions(self::defaultActions())
            ->bulkActions(self::defaultBulkActions());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiliais::route('/'),
            'create' => Pages\CreateFilial::route('/create'),
            'view' => Pages\ViewFilial::route('/{record}'),
            'edit' => Pages\EditFilial::route('/{record}/edit'),
        ];
    }
}
