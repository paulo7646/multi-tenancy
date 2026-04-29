<?php

namespace App\Filament\Client\Resources;

use Filament\Resources\Resource;
use Filament\Tables;

abstract class BaseResource extends Resource
{


    public static function tableWithDefaults(Table $table, array $columns): Table
    {
        return $table
            ->columns($columns)
            ->actions(self::defaultActions())
            ->bulkActions(self::defaultBulkActions());
    }

    public static function defaultActions(): array
    {
        return [
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ];
    }

    public static function defaultBulkActions(): array
    {
        return [
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ];
    }
}