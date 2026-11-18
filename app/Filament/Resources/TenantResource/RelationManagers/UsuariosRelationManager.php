<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Filament\Resources\TenantResource;
use App\Models\Tenant;
use App\Sistema\UsuariosDaEmpresa;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Aba "Usuários" da empresa: os usuários moram no banco do cliente, que o pai não enxerga.
 * Não há relação Eloquent: o datagrid e as ações falam com {@see UsuariosDaEmpresa}, que roda
 * dentro do banco da empresa. O "relationship" abaixo só satisfaz o RelationManager do Filament.
 */
class UsuariosRelationManager extends RelationManager
{
    protected static string $relationship = 'userLicenses';

    protected static ?string $title = 'Clientes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-users';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    private function servico(): UsuariosDaEmpresa
    {
        /** @var Tenant $empresa */
        $empresa = $this->getOwnerRecord();

        return new UsuariosDaEmpresa($empresa);
    }

    /**
     * @param  array<string, mixed>  $cliente
     */
    private function urlDoCliente(array $cliente): string
    {
        return TenantResource::getUrl('cliente', ['record' => $this->getOwnerRecord(), 'cliente' => $cliente['id']]);
    }

    private function podeAlterar(): bool
    {
        return (bool) auth()->user()?->can('update', $this->getOwnerRecord());
    }

    /**
     * Executa a operação e mostra o resultado; erro de validação ou de regra vira aviso na tela
     * e mantém o modal aberto para corrigir.
     */
    public static function tentar(callable $operacao, string $sucesso): void
    {
        try {
            $operacao();
        } catch (ValidationException $e) {
            Notification::make()->title('Dados inválidos')->body(collect($e->errors())->flatten()->implode(' '))->danger()->send();

            throw new Halt;
        } catch (RuntimeException $e) {
            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

            throw new Halt;
        } catch (Throwable $e) {
            report($e);

            Notification::make()->title('Falha ao acessar o banco da empresa')->body('Não foi possível concluir. O detalhe ficou registrado no log.')->danger()->send();

            throw new Halt;
        }

        Notification::make()->title($sucesso)->success()->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel('Usuário')
            ->pluralModelLabel('Usuários')
            ->recordTitleAttribute('name')
            // o padrão do RelationManager exige Model; aqui os registros são arrays. Clicar na linha abre o cliente.
            ->recordAction(null)
            ->recordUrl(fn (array $record): string => $this->urlDoCliente($record))
            ->records(function (?string $search, int $page, int|string $recordsPerPage, ?string $sortColumn, ?string $sortDirection) {
                $porPagina = (int) $recordsPerPage ?: 10;

                try {
                    $r = $this->servico()->listar($search, $page, $porPagina, $sortColumn ?? 'name', $sortDirection ?? 'asc');
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Sem acesso ao banco da empresa')->body('Não foi possível ler os clientes. O detalhe ficou registrado no log.')->danger()->send();

                    return new LengthAwarePaginator([], 0, $porPagina, $page);
                }

                return new LengthAwarePaginator(
                    collect($r['dados'])->mapWithKeys(fn (array $u) => [$u['id'] => $u])->all(),
                    $r['total'],
                    $porPagina,
                    $page,
                );
            })
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->sortable()->copyable(),
                IconColumn::make('administrador')->label('Administrador')->boolean(),
                TextColumn::make('created_at')->label('Criado em')->sortable()
                    ->formatStateUsing(fn (?string $state) => $state ? Carbon::parse($state)->format('d/m/Y H:i') : '—'),
            ])
            ->defaultSort('name')
            ->headerActions([
                Action::make('criar')
                    ->label('Novo usuário')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => $this->podeAlterar())
                    ->modalHeading('Novo usuário')
                    ->modalSubmitActionLabel('Criar')
                    ->schema(static::campos(senhaObrigatoria: true))
                    ->action(fn (array $data) => static::tentar(fn () => $this->servico()->criar($data), 'Cliente criado')),
            ])
            ->recordActions([
                Action::make('consultar')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (array $record) => $this->urlDoCliente($record)),

                Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn () => $this->podeAlterar())
                    ->modalHeading(fn (array $record) => 'Editar '.$record['name'])
                    ->modalSubmitActionLabel('Salvar')
                    ->fillForm(fn (array $record) => ['name' => $record['name'], 'email' => $record['email']])
                    ->schema(fn (array $record) => static::campos(senhaObrigatoria: false, emailTravado: (bool) $record['administrador']))
                    ->action(fn (array $record, array $data) => static::tentar(fn () => $this->servico()->atualizar($record['id'], $data), 'Cliente atualizado')),

                Action::make('excluir')
                    ->label('Excluir')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (array $record) => $this->podeAlterar() && ! $record['administrador'])
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record) => 'Excluir '.$record['name'].'?')
                    ->modalDescription('O cliente é apagado do banco da empresa e não poderá mais entrar. As licenças dele continuam cadastradas.')
                    ->action(fn (array $record) => static::tentar(fn () => $this->servico()->excluir($record['id']), 'Cliente excluído')),
            ])
            ->emptyStateHeading('Nenhum usuário')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return array<int, TextInput>
     */
    public static function campos(bool $senhaObrigatoria, bool $emailTravado = false): array
    {
        return [
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)
                ->readOnly($emailTravado)
                ->helperText($emailTravado ? 'O e-mail do administrador da empresa não pode ser alterado.' : null),
            TextInput::make('password')->label('Senha')->password()->revealable()
                ->required($senhaObrigatoria)->minLength(8)->maxLength(255)
                ->helperText($senhaObrigatoria ? null : 'Deixe em branco para manter a senha atual.'),
        ];
    }
}
