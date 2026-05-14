<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $pluralLabel = 'Empresas';

    protected static ?string $modelLabel = 'Empresa';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Usuario')
                    ->description('Configuração de Usuario Admin')
                    ->icon('heroicon-s-user')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->default('admin'),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->default('admin@gmail.com')
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->default('admin@123')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Empresa')
                    ->description('Confiração de empresa')
                    ->icon('heroicon-m-globe-alt')
                    ->schema([
                        TextInput::make('domain')
                            ->required()
                            ->suffixAction(
                                Action::make('Url')
                                    ->icon('heroicon-m-globe-alt')
                                    ->url(function ($record, $state) {
                                        return "http://{$state}";
                                    })
                                    ->openUrlInNewTab()
                            )
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('impersonate')
                    ->label('Entrar')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuario')
                            ->options(function (Tenant $record) {
                                tenancy()->initialize($record);
                                $users = \App\Models\User::all()->pluck('name', 'id')->toArray();
                                tenancy()->end();
                                return $users;
                            })
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        $domain = $record->domains()->first()->domain;
                        $token = Str::random(40);
                        Cache::put("impersonate:{$token}", $data['user_id'], now()->addMinutes(5));
                        $url = "http://{$domain}/impersonate?token={$token}";
                        redirect()->away($url)->send();
                        exit;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
