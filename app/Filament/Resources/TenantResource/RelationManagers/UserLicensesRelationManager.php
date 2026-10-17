<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserLicenseResource;
use App\Models\UserLicense;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UserLicensesRelationManager extends RelationManager
{
    protected static string $relationship = 'userLicenses';

    protected static ?string $title = 'Licenças';

    protected static ?string $modelLabel = 'Licença';

    protected static ?string $pluralModelLabel = 'Licenças';

    // Sobrescreve a query para incluir licenças globais (tenant_id = null) além das específicas do tenant.
    protected function getTableQuery(): Builder
    {
        $tenantId = $this->getOwnerRecord()->getKey();

        return UserLicense::query()
            ->where(function (Builder $query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                      ->orWhereNull('tenant_id');
            })
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('created_at', 'desc');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_email')
                    ->label('E-mail do Usuário')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Select::make('tenant_id')
                    ->label('Escopo')
                    ->options(function () {
                        $tenantId = $this->getOwnerRecord()->getKey();
                        $tenantName = $this->getOwnerRecord()->displayName();

                        return [
                            $tenantId => "Somente esta empresa ({$tenantName})",
                        ];
                    })
                    ->default(fn () => $this->getOwnerRecord()->getKey())
                    ->placeholder('Global (todas as empresas)')
                    ->nullable()
                    ->helperText('Deixe em branco para criar uma licença global válida em todas as empresas.'),

                TextInput::make('license_key')
                    ->label('Chave da Licença')
                    ->default(fn () => Str::uuid()->toString())
                    ->readOnly()
                    ->required(),

                UserLicenseResource::statusField(),

                DateTimePicker::make('expires_at')
                    ->label('Expira em')
                    ->nullable()
                    ->helperText('Deixe em branco para licença sem expiração.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user_email')
            ->columns([
                TextColumn::make('user_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scope')
                    ->label('Escopo')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->tenant_id ? 'Tenant' : 'Global')
                    ->color(fn (string $state): string => match ($state) {
                        'Tenant' => 'primary',
                        'Global' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('license_key')
                    ->label('Chave')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(),

                UserLicenseResource::statusColumn(),

                TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sem expiração'),
            ])
            ->filters([
                UserLicenseResource::statusFilter(),

                Filter::make('global_only')
                    ->label('Apenas Globais')
                    ->query(fn (Builder $query) => $query->whereNull('tenant_id')),

                Filter::make('tenant_only')
                    ->label('Apenas desta Empresa')
                    ->query(fn (Builder $query) => $query->whereNotNull('tenant_id')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nova Licença'),
            ])
            ->recordActions([
                UserLicenseResource::statusActions(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
