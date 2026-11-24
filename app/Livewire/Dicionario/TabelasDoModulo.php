<?php

namespace App\Livewire\Dicionario;

use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Models\Md\MdTabela;
use App\Sistema\Permissoes;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Aba "Tabelas" da tela do módulo: as tabelas dele, com abrir, alterar, excluir e nova tabela
 * (o dicionário só funciona em ambiente local, para super_admin).
 */
class TabelasDoModulo extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public int $moduloId;

    private function permitido(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) auth()->user()?->hasRole(Permissoes::superAdmin());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => MdTabela::query()->where('modulo_id', $this->moduloId)->withCount('campos')->orderBy('nome_fisico'))
            ->heading('Tabelas do módulo')
            ->columns([
                TextColumn::make('nome')
                    ->label('Tabela')
                    ->weight('semibold')
                    ->color('primary')
                    ->searchable()
                    ->url(fn (MdTabela $record) => MdTabelaResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('nome_fisico')->label('Nome físico')->badge()->color('gray')->searchable(),
                TextColumn::make('campos_count')->label('Campos'),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->state(fn (MdTabela $record) => $record->generated_at ? 'Gerada' : 'Não gerada')
                    ->color(fn (string $state) => $state === 'Gerada' ? 'success' : 'warning'),
            ])
            ->headerActions([
                Action::make('nova')
                    ->label('Nova tabela')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => $this->permitido())
                    ->url(fn () => MdTabelaResource::getUrl('create', ['modulo_id' => $this->moduloId])),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->permitido())
                    ->url(fn (MdTabela $record) => MdTabelaResource::getUrl('edit', ['record' => $record])),
                MdTabelaResource::excluir(DeleteAction::make())->visible(fn () => $this->permitido()),
            ])
            ->emptyStateHeading('Este módulo ainda não tem tabelas')
            ->paginated(false);
    }
}
