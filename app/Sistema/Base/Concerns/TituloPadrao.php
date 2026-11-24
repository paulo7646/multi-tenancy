<?php

namespace App\Sistema\Base\Concerns;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Título padrão das telas dos Resources, igual em todo o sistema (as do dicionário e as feitas à mão):
 *  - lista (datagrid): "Gerenciamento de Empresas"
 *  - incluir e alterar: "Manutenção de Empresa"
 *  - visualizar:        "Visualização de Empresa"
 * O nome vem do Resource (rótulo no plural na lista, no singular nas demais).
 */
trait TituloPadrao
{
    public function getTitle(): string|Htmlable
    {
        $resource = static::getResource();

        return match (true) {
            $this instanceof CreateRecord, $this instanceof EditRecord => 'Manutenção de '.$resource::getTitleCaseModelLabel(),
            $this instanceof ViewRecord => 'Visualização de '.$resource::getTitleCaseModelLabel(),
            default => 'Gerenciamento de '.$resource::getTitleCasePluralModelLabel(),
        };
    }
}
