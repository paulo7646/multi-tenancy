<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\SituacaoResource\Pages;
use App\Models\Situacao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;

class SituacaoResource extends Resource
{
    protected static ?string $model = Situacao::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Situações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Select::make('icon')
                ->searchable()
                ->options(fn () => self::getHeroIcons())
                ->getOptionLabelUsing(fn ($value) => str($value)->after('heroicon-o-')->headline()),
                Forms\Components\Select::make('color')
                    ->label('Cor')
                    ->options([
                        'primary' => '🔵 Primary',
                        'secondary' => '🟣 Secondary',
                        'success' => '🟢 Success',
                        'danger' => '🔴 Danger',
                        'warning' => '🟡 Warning',
                        'info' => '🔷 Info',
                        'gray' => '⚪ Gray',
                    ])
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    public static function getHeroIcons(): array
    {
        return [
            'heroicon-o-check' => 'check',
            'heroicon-m-x-circle' => 'x-circle',
            'heroicon-m-x-mark' => 'x-mark',
        ];
    }


    public static function table(Table $table): Table
    {
        return self::tableWithDefaults($table, [
            Tables\Columns\TextColumn::make('nome')
                    ->searchable(),
            Tables\Columns\TextColumn::make('icon')
                    ->searchable(),
            Tables\Columns\TextColumn::make('color')
                    ->searchable(),
            Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSituacaos::route('/'),
            'create' => Pages\CreateSituacao::route('/create'),
            'view' => Pages\ViewSituacao::route('/{record}'),
            'edit' => Pages\EditSituacao::route('/{record}/edit'),
        ];
    }
}
