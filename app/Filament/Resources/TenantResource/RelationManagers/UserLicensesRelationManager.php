<?php

namespace App\Filament\Resources\TenantResource\RelationManagers;

use App\Models\UserLicense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_email')
                    ->label('E-mail do Usuário')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('tenant_id')
                    ->label('Escopo')
                    ->options(function () {
                        $tenantId = $this->getOwnerRecord()->getKey();
                        $tenantName = $this->getOwnerRecord()->name
                            ?? $this->getOwnerRecord()->domain
                            ?? $tenantId;

                        return [
                            $tenantId => "Somente esta empresa ({$tenantName})",
                        ];
                    })
                    ->default(fn () => $this->getOwnerRecord()->getKey())
                    ->placeholder('Global (todas as empresas)')
                    ->nullable()
                    ->helperText('Deixe em branco para criar uma licença global válida em todas as empresas.'),

                Forms\Components\TextInput::make('license_key')
                    ->label('Chave da Licença')
                    ->default(fn () => Str::uuid()->toString())
                    ->readOnly()
                    ->required(),

                Forms\Components\Toggle::make('status')
                    ->label('Licença Ativa')
                    ->default(true)
                    ->afterStateHydrated(fn ($component, $state) => $component->state($state === 'active'))
                    ->dehydrateStateUsing(fn ($state) => $state ? 'active' : 'inactive'),

                Forms\Components\DateTimePicker::make('expires_at')
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
                Tables\Columns\TextColumn::make('user_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('scope')
                    ->label('Escopo')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->tenant_id ? 'Tenant' : 'Global')
                    ->color(fn (string $state): string => match ($state) {
                        'Tenant' => 'primary',
                        'Global' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('license_key')
                    ->label('Chave')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Ativa' : 'Inativa'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expira em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Sem expiração'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                    ]),

                Tables\Filters\Filter::make('global_only')
                    ->label('Apenas Globais')
                    ->query(fn (Builder $query) => $query->whereNull('tenant_id')),

                Tables\Filters\Filter::make('tenant_only')
                    ->label('Apenas desta Empresa')
                    ->query(fn (Builder $query) => $query->whereNotNull('tenant_id')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nova Licença'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
