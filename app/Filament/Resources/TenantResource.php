<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Resources\TenantResource\Pages\ViewTenant;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\RelationManagers\UserLicensesRelationManager;
use App\Models\Tenant;
use App\Sistema\AtualizadorDeClientes;
use Filament\Notifications\Notification;
use Throwable;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $pluralLabel = 'Empresas';

    protected static ?string $modelLabel = 'Empresa';

    protected static string | \UnitEnum | null $navigationGroup = 'Empresa';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Empresa')
                    ->description('Confiração de empresa')
                    ->icon('heroicon-m-globe-alt')
                    ->schema([
                        TextInput::make('domain')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug(Str::before($state, '.')));
                                }
                            })
                            ->suffixAction(
                                Action::make('Url')
                                    ->icon('heroicon-m-globe-alt')
                                    ->url(function ($record, $state) {
                                        return "http://{$state}";
                                    })
                                    ->openUrlInNewTab()
                            ),

                        TextInput::make('slug')
                            ->label('Pasta do frontend')
                            ->required()
                            ->alphaDash()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            // Empresas antigas podem estar sem pasta: aí o campo fica liberado na edição
                            ->disabled(fn (string $operation, ?Tenant $record) => $operation === 'edit' && filled($record?->slug))
                            ->prefix('views/clientes/')
                            ->helperText('Pasta Vue criada automaticamente para esta empresa. Não pode ser alterada depois.'),

                        TextInput::make('qtd_licencas')
                            ->label('Quantidade de licenças')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->helperText('Máximo de licenças ATIVAS da empresa. Deixe vazio para não limitar.'),

                        Toggle::make('is_active')
                            ->label('Empresa ativa')
                            ->default(true)
                            ->helperText('Desative para bloquear o acesso de todos os usuários desta empresa.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['userLicenses as active_licenses_count' => fn ($q) => $q->active()]))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),

                TextColumn::make('slug')
                    ->label('Pasta')
                    ->searchable(),

                TextColumn::make('domain')
                    ->label('Domínio')
                    ->searchable(),

                TextColumn::make('qtd_licencas')
                    ->label('Licenças ativas')
                    ->badge()
                    ->state(fn (Tenant $record) => $record->active_licenses_count . ' / ' . ($record->qtd_licencas ?? '∞'))
                    ->color(fn (Tenant $record) => $record->qtd_licencas !== null && $record->active_licenses_count >= $record->qtd_licencas ? 'danger' : 'success'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make(),

                DeleteAction::make(),

                Action::make('atualizar')
                    ->label('Atualizar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Tenant $record) => (bool) auth()->user()?->can('update', $record))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $record) => 'Atualizar ' . $record->displayName())
                    ->modalDescription('Roda as migrations pendentes no banco deste cliente.')
                    ->action(function (Tenant $record) {
                        try {
                            $resultado = AtualizadorDeClientes::atualizar($record);

                            Notification::make()->title('Cliente atualizado')->body(AtualizadorDeClientes::mensagem($resultado, false))->success()->send();
                        } catch (Throwable $e) {
                            report($e);

                            Notification::make()->title('A atualização falhou')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),

                Action::make('impersonate')
                    ->label('Entrar')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->schema([
                        Select::make('user_id')
                            ->label('Usuario')
                            ->options(function (Tenant $record) {

                                tenancy()->initialize($record);

                                $users = User::all()
                                    ->pluck('name', 'id')
                                    ->toArray();

                                tenancy()->end();

                                return $users;
                            })
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data, $livewire) {

                        $token = Str::random(40);

                        Cache::store('central')->put(
                            "impersonate:{$token}",
                            $data['user_id'],
                            now()->addMinutes(5)
                        );

                        $domain = $record->domains()->first()->domain;

                        $url = "http://{$domain}/impersonate?token={$token}";

                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UserLicensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'view' => ViewTenant::route('/{record}'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
