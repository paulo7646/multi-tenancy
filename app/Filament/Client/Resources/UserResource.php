<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\UserResource\Pages;
use App\Models\Filial;
use App\Models\Licenca;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;


class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $navigationGroup = 'Configurações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Forms\Components\Select::make('filial_id')
                    ->label('Filial')
                    ->options(fn() => Filial::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                    ->searchable()
                    ->nullable()
                    ->hidden(fn() => auth()->user()->getActiveFilialId())
                    ->placeholder('Sem filial (acesso a todas)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('licenca.nome')
                    ->label('Licença')
                    ->badge()
                    ->color(fn($record) => $record->licenca?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('license_status')
                    ->label('Status da Licença')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $tenantId = tenant()?->getTenantKey();
                        if (!$tenantId) {
                            return 'Sem Licença';
                        }

                        $license = \App\Models\UserLicense::where('user_email', $record->email)
                            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
                            ->orderByRaw('CASE WHEN tenant_id IS NOT NULL THEN 0 ELSE 1 END')
                            ->first();

                        if (!$license) {
                            return 'Sem Licença';
                        }

                        if ($license->isActive()) {
                            return 'Ativa';
                        }

                        if ($license->status === 'active' && $license->expires_at?->isPast()) {
                            return 'Vencida';
                        }

                        return 'Inativa';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Ativa' => 'success',
                        'Vencida' => 'warning',
                        'Inativa' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('filial.nome')
                    ->label('Filial')
                    ->badge()
                    ->color('info')
                    ->default('Todas'),
            ])
            ->filters([
                //
            ])
            ->actions(self::defaultActions())
            ->bulkActions(self::defaultBulkActions());
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
