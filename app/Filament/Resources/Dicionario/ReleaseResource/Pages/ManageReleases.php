<?php

namespace App\Filament\Resources\Dicionario\ReleaseResource\Pages;

use App\Filament\Resources\Dicionario\ReleaseResource;
use App\Models\Md\Modulo;
use App\Sistema\Base\Concerns\TituloPadrao;
use App\Sistema\Releases\Publicador;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\QueryException;
use RuntimeException;

class ManageReleases extends ManageRecords
{
    use TituloPadrao;

    protected static string $resource = ReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Publicar pacote')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->modalHeading('Publicar pacote de um módulo')
                ->modalSubmitActionLabel('Publicar')
                ->using(function (array $data) {
                    try {
                        return Publicador::publicar(Modulo::findOrFail($data['modulo_id']), $data['notas'] ?? null);
                    } catch (RuntimeException $e) {
                        Notification::make()->title('Não foi possível publicar')->body($e->getMessage())->warning()->send();

                        throw new Halt;
                    } catch (QueryException $e) {
                        // duas publicações ao mesmo tempo: a segunda bate no unique (módulo, versão)
                        report($e);
                        Notification::make()->title('Não foi possível publicar')->body('Outra publicação deste módulo acabou de acontecer. Tente de novo.')->warning()->send();

                        throw new Halt;
                    }
                })
                ->successNotificationTitle('Pacote publicado'),
        ];
    }
}
