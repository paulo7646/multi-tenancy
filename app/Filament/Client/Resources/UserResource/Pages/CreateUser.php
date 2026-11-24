<?php

namespace App\Filament\Client\Resources\UserResource\Pages;

use App\Filament\Client\Resources\UserResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use TituloPadrao;

    protected static string $resource = UserResource::class;
}
