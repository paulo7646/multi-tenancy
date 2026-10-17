<?php

namespace App\Filament\Client\Resources;

use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Table;

abstract class BaseResource extends Resource
{
    public static function tableWithDefaults(Table $table, array $columns): Table
    {
        return $table
            ->columns($columns)
            ->recordActions(self::defaultActions())
            ->toolbarActions(self::defaultBulkActions());
    }

    public static function defaultActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function defaultBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ];
    }
}
