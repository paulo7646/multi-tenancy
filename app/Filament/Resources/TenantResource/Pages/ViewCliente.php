<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\RelationManagers\UsuariosRelationManager;
use App\Models\Tenant;
use App\Sistema\UsuariosDaEmpresa;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use RuntimeException;

/**
 * Visualização de um cliente (usuário do banco da empresa), com a aba Geral.
 * Os dados vêm do banco da empresa via {@see UsuariosDaEmpresa}. As licenças dele ficam na aba
 * Licenças da própria empresa.
 *
 * @property Tenant $record
 */
class ViewCliente extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TenantResource::class;

    /** @var array<string, mixed> */
    #[Locked]
    public array $usuario = [];

    public function mount(int|string $record, int|string $cliente): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()?->can('view', $this->record), 403);

        try {
            $this->usuario = (new UsuariosDaEmpresa($this->record))->consultar($cliente);
        } catch (RuntimeException) {
            abort(404);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->usuario['name'];
    }

    public function getBreadcrumbs(): array
    {
        return [
            TenantResource::getUrl('index') => 'Empresas',
            TenantResource::getUrl('view', ['record' => $this->record]) => $this->record->displayName(),
            'Cliente',
        ];
    }

    private function servico(): UsuariosDaEmpresa
    {
        return new UsuariosDaEmpresa($this->record);
    }

    private function podeAlterar(): bool
    {
        return (bool) auth()->user()?->can('update', $this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voltar')
                ->label('Voltar para a empresa')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => TenantResource::getUrl('view', ['record' => $this->record])),

            Action::make('excluir')
                ->label('Excluir')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => $this->podeAlterar() && ! $this->usuario['administrador'])
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Excluir '.$this->usuario['name'].'?')
                ->modalDescription('O cliente é apagado do banco da empresa e não poderá mais entrar. As licenças dele continuam cadastradas.')
                ->action(function () {
                    UsuariosRelationManager::tentar(fn () => $this->servico()->excluir($this->usuario['id']), 'Cliente excluído');

                    $this->redirect(TenantResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('cliente')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Geral')
                        ->icon('heroicon-o-identification')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('name')->label('Nome')->state(fn () => $this->usuario['name']),
                            TextEntry::make('email')->label('E-mail')->state(fn () => $this->usuario['email'])->copyable(),
                            TextEntry::make('empresa')->label('Empresa')->state(fn () => $this->record->displayName()),
                            TextEntry::make('perfil')->label('Perfil')
                                ->state(fn () => $this->usuario['administrador'] ? 'Administrador da empresa' : 'Cliente')
                                ->badge()
                                ->color(fn () => $this->usuario['administrador'] ? 'warning' : 'gray'),
                            TextEntry::make('created_at')->label('Criado em')
                                ->state(fn () => $this->usuario['created_at'] ? Carbon::parse($this->usuario['created_at'])->format('d/m/Y H:i') : '—'),
                        ]),
                ]),
        ]);
    }
}
