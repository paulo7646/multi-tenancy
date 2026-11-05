<?php

namespace App\Plataforma\Resources\AssinaturaResource\Pages;

use App\Plataforma\Assinatura\AssinaturaException;
use App\Plataforma\Models\Arquivo;
use App\Plataforma\Resources\AssinaturaResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAssinaturas extends ListRecords
{
    protected static string $resource = AssinaturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('novo')
                ->label('Novo pedido')
                ->icon('heroicon-o-plus')
                ->modalWidth('3xl')
                ->schema(AssinaturaResource::camposDoPedido())
                ->action(function (array $data) {
                    try {
                        $versao = Arquivo::findOrFail($data['arquivo_id'])->versaoAtual;
                        $pedido = AssinaturaResource::criarPedido($data, $versao);

                        Notification::make()->title($pedido->status === 'em_andamento' ? 'Pedido enviado para assinatura' : 'Pedido salvo como rascunho')
                            ->actions([Action::make('abrir')->label('Abrir')->url(AssinaturaResource::getUrl('view', ['record' => $pedido]))])
                            ->success()->send();
                    } catch (AssinaturaException $e) {
                        Notification::make()->title('Não foi possível criar o pedido')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
