<?php

namespace App\Filament\Resources\UserLicenseResource\Pages;

use App\Filament\Resources\UserLicenseResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Resources\Pages\CreateRecord;

class CreateUserLicense extends CreateRecord
{
    use TituloPadrao;

    protected static string $resource = UserLicenseResource::class;
}
