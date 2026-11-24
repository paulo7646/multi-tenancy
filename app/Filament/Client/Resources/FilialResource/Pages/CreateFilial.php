<?php

namespace App\Filament\Client\Resources\FilialResource\Pages;

use App\Filament\Client\Resources\FilialResource;
use App\Sistema\Base\Concerns\TituloPadrao;
use Filament\Resources\Pages\CreateRecord;

class CreateFilial extends CreateRecord
{
    use TituloPadrao;

    protected static string $resource = FilialResource::class;
}
