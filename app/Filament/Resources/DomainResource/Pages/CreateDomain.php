<?php

namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Resources\Pages\CreateRecord;

class CreateDomain extends CreateRecord
{
    use TituloPadrao;

    protected static string $resource = DomainResource::class;
}
