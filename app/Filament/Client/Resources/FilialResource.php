<?php

namespace App\Filament\Client\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Client\Resources\FilialResource\Pages\ListFiliais;
use App\Filament\Client\Resources\FilialResource\Pages\CreateFilial;
use App\Filament\Client\Resources\FilialResource\Pages\ViewFilial;
use App\Filament\Client\Resources\FilialResource\Pages\EditFilial;
use App\Models\Filial;
use Filament\Tables\Table;

class FilialResource extends BaseResource
{
    protected static ?string $model = Filial::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Filiais';

    protected static string | \UnitEnum | null $navigationGroup = 'Configurações';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Textarea::make('descricao')
                    ->rows(3)
                    ->columnSpanFull(),
                Toggle::make('ativo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('descricao')
                    ->limit(50)
                    ->toggleable(),
                IconColumn::make('ativo')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Usuários')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('ativo'),
            ])
            ->recordActions(self::defaultActions())
            ->toolbarActions(self::defaultBulkActions());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiliais::route('/'),
            'create' => CreateFilial::route('/create'),
            'view' => ViewFilial::route('/{record}'),
            'edit' => EditFilial::route('/{record}/edit'),
        ];
    }
}
