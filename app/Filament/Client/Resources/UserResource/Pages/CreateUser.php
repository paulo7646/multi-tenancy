<?php

namespace App\Filament\Client\Resources\UserResource\Pages;

use App\Filament\Client\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
