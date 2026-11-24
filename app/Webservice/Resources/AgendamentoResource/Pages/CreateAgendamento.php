<?php

namespace App\Webservice\Resources\AgendamentoResource\Pages;

use App\Sistema\Base\Concerns\TituloPadrao;
use App\Webservice\Resources\AgendamentoResource;
use App\Webservice\Resources\AgendamentoResource\Pages\Concerns\ValidaParametros;
use Filament\Resources\Pages\CreateRecord;

class CreateAgendamento extends CreateRecord
{
    use TituloPadrao;
    use ValidaParametros;

    protected static string $resource = AgendamentoResource::class;

    protected function beforeCreate(): void
    {
        $this->validarParametrosObrigatorios();
    }
}
