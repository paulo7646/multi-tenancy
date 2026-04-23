<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\SituacaoResource\Pages;
use App\Models\Situacao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                Forms\Components\TextInput::make('icon')
                    ->maxLength(255),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
