<?php

namespace App\Filament\Pages;

use App\Sistema\AtualizadorDeClientes;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Throwable;

/**
 * Dashboard do painel admin, com o botão que atualiza o banco de todos os clientes.
 */
class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('atualizarClientes')
                ->label('Atualizar todos os clientes')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => (bool) auth()->user()?->can('update_tenant'))
                ->requiresConfirmation()
                ->modalHeading('Atualizar todos os clientes')
                ->modalDescription('Roda as migrations pendentes no banco de cada cliente.')
                ->action(function () {
                    try {
                        $resultado = AtualizadorDeClientes::atualizar();

                        Notification::make()->title('Clientes atualizados')->body(AtualizadorDeClientes::mensagem($resultado, true))->success()->send();
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()->title('A atualização falhou')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
