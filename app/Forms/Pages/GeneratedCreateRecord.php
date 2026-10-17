<?php

namespace App\Forms\Pages;

use Filament\Resources\Pages\CreateRecord;

abstract class GeneratedCreateRecord extends CreateRecord
{
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
