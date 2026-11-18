<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Releases\Aplicador;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Aba "Módulos" da empresa: cadastrar um módulo aqui libera o acesso da empresa a ele. Sem cadastro
 * (e sem ser padrão, como a Estrutura) a empresa nem acessa o módulo, nem o vê no menu.
 */
class ModulosRelationManager extends RelationManager
{
    protected static string $relationship = 'modulos';

    protected static ?string $title = 'Módulos';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-squares-2x2';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    private function podeAlterar(): bool
    {
        return (bool) auth()->user()?->can('update', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        $padrao = Modulo::query()->where('padrao', true)->orderBy('nome')->pluck('nome')->all();

        return $table
            ->modelLabel('Módulo')
            ->pluralModelLabel('Módulos')
            ->recordTitleAttribute('nome')
            ->description($padrao === [] ? null : 'Sempre liberados para todas as empresas: '.implode(', ', $padrao).'.')
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('nome'))
            ->columns([
                TextColumn::make('nome')->label('Módulo')->searchable()->sortable(),
                TextColumn::make('prefixo')->label('Prefixo')->badge(),
                IconColumn::make('padrao')->label('Padrão')->boolean(),
                TextColumn::make('descricao')->label('Descrição')->limit(60)->placeholder('—'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Adicionar módulo')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => $this->podeAlterar())
                    ->preloadRecordSelect()
                    // a lista só mostra os módulos permitidos, mas o id vem do navegador: confere no servidor
                    ->before(function (array $data, $action) {
                        $permitido = Modulo::query()
                            ->whereKey($data['recordId'] ?? null)
                            ->where('banco', Banco::Tenant->value)
                            ->where('padrao', false)
                            ->exists();

                        if (! $permitido) {
                            Notification::make()->title('Módulo não permitido')->body('Só módulos de clientes que não são padrão podem ser cadastrados na empresa.')->danger()->send();

                            $action->halt();
                        }
                    })
                    // a empresa já recebe o que foi publicado do módulo (as próximas releases vêm pela aba Releases dela)
                    ->after(function () {
                        try {
                            $aplicadas = Aplicador::aplicar($this->getOwnerRecord()->fresh());

                            if ($aplicadas > 0) {
                                Notification::make()->title("{$aplicadas} release(s) aplicada(s) na empresa")->success()->send();
                            }
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()->title('Módulo adicionado, mas a release falhou')->body('O detalhe ficou no log. Use "Atualizar" na aba Releases da empresa para tentar de novo.')->danger()->persistent()->send();
                        }
                    })
                    // só as colunas do seletor: o DISTINCT dele não funciona com as colunas JSON do módulo no PostgreSQL
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query
                        ->select(['modulos.id', 'modulos.nome'])
                        ->where('banco', Banco::Tenant->value)
                        ->where('padrao', false)
                        ->orderBy('nome')),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remover')
                    ->modalHeading(fn (Modulo $record) => 'Remover o módulo '.$record->nome.'?')
                    ->modalDescription('A empresa perde o acesso ao módulo: ele some do menu e das permissões dela. Os dados já gravados não são apagados.')
                    ->visible(fn () => $this->podeAlterar()),
            ])
            ->emptyStateHeading('Nenhum módulo cadastrado')
            ->emptyStateDescription('Use "Adicionar módulo" para liberar um módulo para esta empresa.');
    }
}
