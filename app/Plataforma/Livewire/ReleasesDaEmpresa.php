<?php

namespace App\Plataforma\Livewire;

use App\Models\Md\MdRelease;
use App\Models\Tenant;
use App\Sistema\Permissoes;
use App\Sistema\Releases\Aplicador;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Throwable;

/**
 * Aba "Releases" das configurações do cliente: as releases que a base pai publicou para os módulos
 * desta empresa, e o botão que aplica as pendentes no banco dela. Só aparecem módulos que a empresa tem.
 */
class ReleasesDaEmpresa extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /** @var list<int>|null */
    protected ?array $aplicadas = null;

    private function podeAtualizar(): bool
    {
        return (bool) auth()->user()?->hasRole(Permissoes::superAdmin());
    }

    private function empresa(): Tenant
    {
        return tenant();
    }

    /**
     * @return list<int>
     */
    private function aplicadas(): array
    {
        return $this->aplicadas ??= Aplicador::idsAplicados($this->empresa());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Releases dos seus módulos')
            ->description('Atualizações que a base pai publicou. Cada empresa recebe só as dos módulos que tem.')
            ->query(fn () => MdRelease::query()
                ->with('modulo')
                ->whereIn('modulo_id', Aplicador::modulosDaEmpresa($this->empresa()))
                ->orderByDesc('publicado_em'))
            ->columns([
                TextColumn::make('modulo.nome')->label('Módulo'),
                TextColumn::make('versao')->label('Versão')->prefix('v'),
                TextColumn::make('notas')->limit(70)->placeholder('—'),
                TextColumn::make('publicado_em')->label('Publicada em')->dateTime('d/m/Y H:i'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (MdRelease $record) => in_array($record->id, $this->aplicadas(), true) ? 'Aplicada' : 'Pendente')
                    ->color(fn (string $state) => $state === 'Aplicada' ? 'success' : 'warning'),
            ])
            ->headerActions([
                Action::make('atualizar')
                    ->label('Atualizar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => $this->podeAtualizar() && Aplicador::pendentes($this->empresa())->isNotEmpty())
                    ->requiresConfirmation()
                    ->modalHeading('Atualizar com as releases pendentes?')
                    ->modalDescription('Aplica no banco da sua empresa as atualizações dos módulos que você tem.')
                    ->action(function () {
                        // roda DDL no banco da empresa: a ação se protege sozinha, não só a página que a hospeda
                        abort_unless($this->podeAtualizar(), 403);

                        try {
                            $aplicadas = Aplicador::aplicar($this->empresa());
                            $this->aplicadas = null;

                            Notification::make()->title("{$aplicadas} release(s) aplicada(s)")->success()->send();
                        } catch (Throwable $e) {
                            report($e);

                            // o detalhe (SQL, caminhos) fica no log; o usuário vê uma mensagem sem infraestrutura
                            Notification::make()->title('A atualização falhou')->body('Não foi possível aplicar as releases. Avise o suporte: o detalhe ficou registrado.')->danger()->persistent()->send();
                        }
                    }),
            ])
            ->emptyStateHeading('Nenhuma release dos seus módulos')
            ->emptyStateDescription('Quando a base pai publicar uma atualização de um módulo seu, ela aparece aqui.')
            ->paginated([10, 25]);
    }
}
