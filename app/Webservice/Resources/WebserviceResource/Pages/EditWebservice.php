<?php

namespace App\Webservice\Resources\WebserviceResource\Pages;

use App\Webservice\Resources\WebserviceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebservice extends EditRecord
{
    protected static string $resource = WebserviceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
