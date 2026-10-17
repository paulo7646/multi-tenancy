<?php

namespace App\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserLicenseResource\Pages\ListUserLicenses;
use App\Filament\Resources\UserLicenseResource\Pages\CreateUserLicense;
use App\Filament\Resources\UserLicenseResource\Pages\ViewUserLicense;
use App\Filament\Resources\UserLicenseResource\Pages\EditUserLicense;
use App\Enums\LicenseStatus;
use App\Models\Tenant;
use App\Models\UserLicense;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserLicenseResource extends Resource
{
    protected static ?string $model = UserLicense::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Licenças de Usuário';

    protected static ?string $modelLabel = 'Licença';

    protected static ?string $pluralModelLabel = 'Licenças de Usuário';

    protected static string | \UnitEnum | null $navigationGroup = 'Empresa';

    /**
     * Badge no menu com as solicitações que ainda não foram analisadas.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendentes = UserLicense::where('status', LicenseStatus::Requested)->count();

        return $pendentes > 0 ? (string) $pendentes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Solicitações cadastradas aguardando análise';
    }

    public static function tenantOptions(): array
    {
        return Tenant::with('domains')->get()
            ->mapWithKeys(fn (Tenant $tenant) => [$tenant->getKey() => $tenant->displayName()])
            ->all();
    }

    /**
     * Select de status com a trava do limite de licenças ativas da empresa.
     */
    public static function statusField(): Select
    {
        return Select::make('status')
            ->label('Status')
            ->options(LicenseStatus::class)
            ->default(LicenseStatus::Active)
            ->required()
            ->native(false)
            ->rules([
                fn (Get $get, ?UserLicense $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                    $status = $value instanceof LicenseStatus ? $value : LicenseStatus::tryFrom((string) $value);

                    if ($status !== LicenseStatus::Active) {
                        return;
                    }

                    $tenant = Tenant::find($get('tenant_id'));

                    if ($tenant?->licenseLimitReached(except: $record?->getKey())) {
                        $fail($tenant->licenseLimitMessage());
                    }
                },
            ]);
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->sortable();
    }

    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label('Status')
            ->options(LicenseStatus::class);
    }

    /**
     * Ações rápidas do fluxo: Em análise -> Ativar / Desativar.
     */
    public static function statusActions(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('analisar')
                ->label('Colocar em análise')
                ->icon(LicenseStatus::InReview->getIcon())
                ->color(LicenseStatus::InReview->getColor())
                ->visible(fn (UserLicense $record) => $record->status === LicenseStatus::Requested)
                ->action(fn (UserLicense $record) => $record->update(['status' => LicenseStatus::InReview])),

            Action::make('ativar')
                ->label('Ativar')
                ->icon(LicenseStatus::Active->getIcon())
                ->color(LicenseStatus::Active->getColor())
                ->requiresConfirmation()
                ->visible(fn (UserLicense $record) => $record->status !== LicenseStatus::Active)
                ->action(function (UserLicense $record, Action $action) {
                    if ($record->tenant?->licenseLimitReached(except: $record->getKey())) {
                        Notification::make()
                            ->title('Limite de licenças atingido')
                            ->body($record->tenant->licenseLimitMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                    }

                    $record->update(['status' => LicenseStatus::Active]);

                    Notification::make()->title('Licença ativada')->success()->send();
                }),

            Action::make('desativar')
                ->label('Desativar')
                ->icon(LicenseStatus::Inactive->getIcon())
                ->color(LicenseStatus::Inactive->getColor())
                ->requiresConfirmation()
                ->visible(fn (UserLicense $record) => $record->status !== LicenseStatus::Inactive)
                ->action(fn (UserLicense $record) => $record->update(['status' => LicenseStatus::Inactive])),
        ])
            ->label('Status')
            ->icon('heroicon-m-arrow-path')
            ->button()
            ->size('sm')
            ->color('gray');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_email')
                    ->label('E-mail do Usuário')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Select::make('tenant_id')
                    ->label('Empresa (Tenant)')
                    ->options(fn () => static::tenantOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->placeholder('Global (todas as empresas)')
                    ->nullable()
                    ->helperText(function (Get $get) {
                        $tenant = Tenant::find($get('tenant_id'));

                        if (! $tenant || $tenant->qtd_licencas === null) {
                            return null;
                        }

                        return "Licenças ativas da empresa: {$tenant->activeLicensesCount()} de {$tenant->qtd_licencas}.";
                    }),
                TextInput::make('license_key')
                    ->label('Chave da Licença')
                    ->default(fn() => Str::uuid()->toString())
                    ->readOnly()
                    ->required(),
                static::statusField(),
                DateTimePicker::make('expires_at')
                    ->label('Expira em')
                    ->nullable()
                    ->helperText('Deixe em branco para licença sem expiração.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tenant.domains'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant_id')
                    ->label('Empresa')
                    ->formatStateUsing(fn (UserLicense $record) => $record->tenant?->displayName())
                    ->placeholder('Global')
                    ->sortable(),
                TextColumn::make('license_key')
                    ->label('Chave')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(),
                static::statusColumn(),
                TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sem expiração'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                static::statusFilter(),
                SelectFilter::make('tenant_id')
                    ->label('Empresa')
                    ->options(fn () => static::tenantOptions())
                    ->searchable(),
            ])
            ->recordActions([
                static::statusActions(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserLicenses::route('/'),
            'create' => CreateUserLicense::route('/create'),
            'view' => ViewUserLicense::route('/{record}'),
            'edit' => EditUserLicense::route('/{record}/edit'),
        ];
    }
}
