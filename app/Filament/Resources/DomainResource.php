<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DomainResource\Pages\CreateDomain;
use App\Filament\Resources\DomainResource\Pages\EditDomain;
use App\Filament\Resources\DomainResource\Pages\ListDomains;
use App\Filament\Resources\DomainResource\Pages\ViewDomain;
use App\Models\Domain;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainResource extends Resource
{
    protected static ?string $model = Domain::class;

    protected static ?string $pluralLabel = 'Dominios';

    protected static ?string $modelLabel = 'Dominio';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Empresa';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('domain')
                    ->required()
                    ->maxLength(255),
                TextInput::make('tenant_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->searchable(),
                TextColumn::make('tenant_id')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListDomains::route('/'),
            'create' => CreateDomain::route('/create'),
            'view' => ViewDomain::route('/{record}'),
            'edit' => EditDomain::route('/{record}/edit'),
        ];
    }
}
