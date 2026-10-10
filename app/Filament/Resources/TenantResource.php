<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers\UserLicensesRelationManager;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $pluralLabel = 'Empresas';

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $navigationGroup = 'Empresa';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Empresa')
                    ->description('Confiração de empresa')
                    ->icon('heroicon-m-globe-alt')
                    ->schema([
                        TextInput::make('domain')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Forms\Get $get, Forms\Set $set) {
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
                            ->disabledOn('edit')
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Pasta')
                    ->searchable(),

                Tables\Columns\TextColumn::make('domain')
                    ->label('Domínio')
                    ->searchable(),

                Tables\Columns\TextColumn::make('qtd_licencas')
                    ->label('Licenças ativas')
                    ->badge()
                    ->state(fn (Tenant $record) => $record->active_licenses_count . ' / ' . ($record->qtd_licencas ?? '∞'))
                    ->color(fn (Tenant $record) => $record->qtd_licencas !== null && $record->active_licenses_count >= $record->qtd_licencas ? 'danger' : 'success'),

                Tables\Columns\IconColumn::make('is_active')
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
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('impersonate')
                    ->label('Entrar')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuario')
                            ->options(function (Tenant $record) {

                                tenancy()->initialize($record);

                                $users = \App\Models\User::all()
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
