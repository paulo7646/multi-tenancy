<?php

namespace App\Webservice\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Tela de edição das telas aninhadas: Voltar leva ao registro pai.
 */
abstract class EditFilho extends EditRecord
{
    protected function getHeaderActions(): array
    {
        /** @var class-string<ResourceFilho> $resource */
        $resource = static::getResource();

        return [
            Action::make('voltar')
                ->label('Voltar')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => $resource::urlPai($this->record)),
            DeleteAction::make()
                ->successRedirectUrl(fn () => $resource::urlPai($this->record)),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
