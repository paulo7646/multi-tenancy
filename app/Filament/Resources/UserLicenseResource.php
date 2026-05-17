<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserLicenseResource\Pages;
use App\Models\Tenant;
use App\Models\UserLicense;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserLicenseResource extends Resource
{
    protected static ?string $model = UserLicense::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Licenças de Usuário';

    protected static ?string $modelLabel = 'Licença';

    protected static ?string $pluralModelLabel = 'Licenças de Usuário';

    protected static ?string $navigationGroup = 'Empresa';

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
                    ->options(Tenant::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Global (todas as empresas)')
                    ->nullable(),
                Forms\Components\TextInput::make('license_key')
                    ->label('Chave da Licença')
                    ->default(fn() => Str::uuid()->toString())
                    ->readOnly()
                    ->required(),
                Forms\Components\Toggle::make('status')
                    ->label('Licença Ativa')
                    ->default(true)
                    ->afterStateHydrated(fn($component, $state) => $component->state($state === 'active'))
                    ->dehydrateStateUsing(fn($state) => $state ? 'active' : 'inactive'),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Expira em')
                    ->nullable()
                    ->helperText('Deixe em branco para licença sem expiração.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Global'),
                Tables\Columns\TextColumn::make('license_key')
                    ->label('Chave')
                    ->limit(20)
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn($state) => $state === 'active' ? 'Ativa' : 'Inativa'),
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Ativa',
                        'inactive' => 'Inativa',
                    ]),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Empresa')
                    ->options(Tenant::pluck('name', 'id'))
                    ->searchable(),
            ])
            ->actions([
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
