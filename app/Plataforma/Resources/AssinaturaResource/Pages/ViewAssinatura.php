<?php

namespace App\Plataforma\Resources\AssinaturaResource\Pages;

use App\Plataforma\Assinatura\AssinaturaException;
use App\Plataforma\Assinatura\Assinaturas;
use App\Plataforma\Models\Assinatura;
use App\Plataforma\Resources\AssinaturaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAssinatura extends ViewRecord
{
    protected static string $resource = AssinaturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviar')
                ->label('Enviar convites')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (Assinatura $record) => $record->status === 'rascunho')
                ->requiresConfirmation()
                ->action(fn (Assinatura $record) => $this->tentar(fn () => Assinaturas::enviar($record), 'Convites enviados')),

            Action::make('certificado')
                ->label('Baixar certificado')
                ->icon('heroicon-o-document-check')
                ->visible(fn (Assinatura $record) => $record->certificado?->versaoAtual !== null)
                ->action(function (Assinatura $record) {
                    $versao = $record->certificado->versaoAtual;

                    return response()->streamDownload(fn () => print $versao->conteudo(), $versao->nome_original, ['Content-Type' => 'application/pdf']);
                }),

            Action::make('documento')
                ->label('Baixar documento')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (Assinatura $record) {
                    $versao = $record->versao;

                    return response()->streamDownload(fn () => print $versao->conteudo(), $versao->nome_original, ['Content-Type' => 'application/pdf']);
                }),

            Action::make('verificar')
                ->label('Página de verificação')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(fn (Assinatura $record) => url("/verificar/{$record->codigo_verificacao}"))
                ->openUrlInNewTab(),

            Action::make('cancelar')
                ->label('Cancelar pedido')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Assinatura $record) => ! in_array($record->status, ['concluido', 'cancelado'], true))
                ->schema([Textarea::make('motivo')->label('Motivo')->required()->maxLength(500)])
                ->action(fn (Assinatura $record, array $data) => $this->tentar(fn () => Assinaturas::cancelar($record, $data['motivo']), 'Pedido cancelado')),
        ];
    }

    protected function tentar(callable $acao, string $sucesso): void
    {
        try {
            $acao();
            $this->record->refresh();
            Notification::make()->title($sucesso)->success()->send();
        } catch (AssinaturaException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
