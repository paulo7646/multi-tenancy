<?php

namespace App\Filament\Resources;

use App\Enums\LicenseStatus;
use App\Filament\Resources\UserLicenseResource\Pages;
use App\Models\Tenant;
use App\Models\UserLicense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserLicenseResource extends Resource
{
    protected static ?string $model = UserLicense::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Licenças de Usuário';

    protected static ?string $modelLabel = 'Licença';

    protected static ?string $pluralModelLabel = 'Licenças de Usuário';

    protected static ?string $navigationGroup = 'Empresa';

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
    public static function statusField(): Forms\Components\Select
    {
        return Forms\Components\Select::make('status')
            ->label('Status')
            ->options(LicenseStatus::class)
            ->default(LicenseStatus::Active)
            ->required()
            ->native(false)
            ->rules([
                fn (Forms\Get $get, ?UserLicense $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
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

    public static function statusColumn(): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->sortable();
    }

    public static function statusFilter(): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('status')
            ->label('Status')
            ->options(LicenseStatus::class);
    }

    /**
     * Ações rápidas do fluxo: Em análise -> Ativar / Desativar.
     */
    public static function statusActions(): Tables\Actions\ActionGroup
    {
        return Tables\Actions\ActionGroup::make([
            Tables\Actions\Action::make('analisar')
                ->label('Colocar em análise')
                ->icon(LicenseStatus::InReview->getIcon())
                ->color(LicenseStatus::InReview->getColor())
                ->visible(fn (UserLicense $record) => $record->status === LicenseStatus::Requested)
                ->action(fn (UserLicense $record) => $record->update(['status' => LicenseStatus::InReview])),

            Tables\Actions\Action::make('ativar')
                ->label('Ativar')
                ->icon(LicenseStatus::Active->getIcon())
                ->color(LicenseStatus::Active->getColor())
                ->requiresConfirmation()
                ->visible(fn (UserLicense $record) => $record->status !== LicenseStatus::Active)
                ->action(function (UserLicense $record, Tables\Actions\Action $action) {
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

            Tables\Actions\Action::make('desativar')
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_email')
                    ->label('E-mail do Usuário')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('tenant_id')
                    ->label('Empresa (Tenant)')
                    ->options(fn () => static::tenantOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->placeholder('Global (todas as empresas)')
                    ->nullable()
                    ->helperText(function (Forms\Get $get) {
                        $tenant = Tenant::find($get('tenant_id'));

                        if (! $tenant || $tenant->qtd_licencas === null) {
                            return null;
                        }

                        return "Licenças ativas da empresa: {$tenant->activeLicensesCount()} de {$tenant->qtd_licencas}.";
                    }),
                Forms\Components\TextInput::make('license_key')
                    ->label('Chave da Licença')
                    ->default(fn() => Str::uuid()->toString())
                    ->readOnly()
                    ->required(),
                static::statusField(),
                Forms\Components\DateTimePicker::make('expires_at')
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
                Tables\Columns\TextColumn::make('user_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant_id')
                    ->label('Empresa')
                    ->formatStateUsing(fn (UserLicense $record) => $record->tenant?->displayName())
                    ->placeholder('Global')
                    ->sortable(),
                Tables\Columns\TextColumn::make('license_key')
                    ->label('Chave')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(),
                static::statusColumn(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sem expiração'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                static::statusFilter(),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Empresa')
                    ->options(fn () => static::tenantOptions())
                    ->searchable(),
            ])
            ->actions([
                static::statusActions(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUserLicenses::route('/'),
            'create' => Pages\CreateUserLicense::route('/create'),
            'view' => Pages\ViewUserLicense::route('/{record}'),
            'edit' => Pages\EditUserLicense::route('/{record}/edit'),
        ];
    }
}
