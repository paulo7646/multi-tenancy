<?php

namespace App\Forms\Pages;

use Filament\Resources\Pages\ListRecords;

abstract class GeneratedListRecords extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return static::getResource()::headerActions();
    }
}
