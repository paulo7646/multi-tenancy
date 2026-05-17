<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\LicencaResource\Pages;
use App\Models\Licenca;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class LicencaResource extends BaseResource
{
    protected static ?string $model = Licenca::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Licenças';

    protected static ?string $modelLabel = 'Licença';

    protected static ?string $pluralModelLabel = 'Licenças';

    protected static ?string $navigationGroup = 'Configurações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descricao')
                    ->label('Descrição')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('icon')
                    ->label('Ícone')
                    ->maxLength(255),
                Forms\Components\Select::make('color')
                    ->label('Cor')
                    ->options([
                        'primary' => 'Primary',
                        'secondary' => 'Secondary',
                        'success' => 'Success',
                        'danger' => 'Danger',
                        'warning' => 'Warning',
                        'info' => 'Info',
                        'gray' => 'Gray',
                    ])
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Toggle::make('ativo')
                    ->label('Ativo')
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
                    ->label('Descrição')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('color')
                    ->label('Cor')
                    ->badge()
                    ->color(fn (string $state): string => $state),
                Tables\Columns\IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuários')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('ativo')
                    ->label('Ativo'),
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
            'index' => Pages\ListLicencas::route('/'),
            'create' => Pages\CreateLicenca::route('/create'),
            'view' => Pages\ViewLicenca::route('/{record}'),
            'edit' => Pages\EditLicenca::route('/{record}/edit'),
        ];
    }
}
