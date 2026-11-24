<?php

namespace App\Webservice\Resources\WebserviceResource\Pages;

use App\Sistema\Base\Concerns\TituloPadrao;
use App\Webservice\Resources\WebserviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebservice extends CreateRecord
{
    use TituloPadrao;

    protected static string $resource = WebserviceResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
