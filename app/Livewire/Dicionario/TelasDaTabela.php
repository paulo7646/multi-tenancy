<?php

namespace App\Livewire\Dicionario;

use App\Filament\Resources\Dicionario\MdFormularioResource;
use App\Filament\Resources\Dicionario\MdGerenciamentoResource;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Sistema\Permissoes;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Abas Formulários e Gerenciamentos da tela da tabela: os da tabela, com abrir, alterar, excluir e novo
 * (o dicionário só funciona em ambiente local, para super_admin).
 */
class TelasDaTabela extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public int $tabelaId;

    /** 'formularios' ou 'gerenciamentos' */
    public string $tipo = 'formularios';

    private function permitido(): bool
    {
        return app()->environment(['local', 'testing']) && (bool) auth()->user()?->hasRole(Permissoes::superAdmin());
    }

    private function ehFormulario(): bool
    {
        return $this->tipo === 'formularios';
    }

    private function urlEditar(MdFormulario|MdGerenciamento $record): string
    {
        return $this->ehFormulario()
            ? MdFormularioResource::getUrl('edit', ['record' => $record])
            : MdGerenciamentoResource::getUrl('edit', ['record' => $record]);
    }

    public function table(Table $table): Table
    {
        $rotulo = $this->ehFormulario() ? 'formulário' : 'gerenciamento';

        $colunas = [
            TextColumn::make('titulo')
                ->label('Título')
                ->weight('semibold')
                ->color('primary')
                ->searchable()
                ->url(fn (MdFormulario|MdGerenciamento $record) => $this->urlEditar($record)),
            TextColumn::make('nome_arquivo')->label('Arquivo')->badge()->color('gray')->suffix('.php'),
            TextColumn::make('nome_fisico')->label('Nome físico')->searchable(),
        ];

        if (! $this->ehFormulario()) {
            $colunas[] = TextColumn::make('formulario.titulo')->label('Formulário')->placeholder('nenhum');
        }

        return $table
            ->query(fn (): Builder => $this->ehFormulario()
                ? MdFormulario::query()->where('md_tabela_id', $this->tabelaId)->orderBy('id')
                : MdGerenciamento::query()->where('md_tabela_id', $this->tabelaId)->with('formulario')->orderBy('ordem'))
            ->heading($this->ehFormulario() ? 'Formulários da tabela' : 'Gerenciamentos da tabela')
            ->columns($colunas)
            ->headerActions([
                Action::make('novo')
                    ->label("Novo {$rotulo}")
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => $this->permitido())
                    ->url(fn () => $this->ehFormulario()
                        ? MdFormularioResource::getUrl('create', ['tabela' => $this->tabelaId])
                        : MdGerenciamentoResource::getUrl('create', ['tabela' => $this->tabelaId])),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Abrir')
                    ->visible(fn () => $this->permitido())
                    ->url(fn (MdFormulario|MdGerenciamento $record) => $this->urlEditar($record)),
                ($this->ehFormulario() ? MdFormularioResource::excluir(DeleteAction::make()) : MdGerenciamentoResource::excluir(DeleteAction::make()))
                    ->visible(fn () => $this->permitido()),
            ])
            ->emptyStateHeading("Nenhum {$rotulo} nesta tabela")
            ->paginated(false);
    }
}
