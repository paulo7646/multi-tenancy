<?php

namespace App\Webservice\Resources;

use App\Webservice\Models\Webservice;
use App\Webservice\RelationManagers\OperacoesRelationManager;
use App\Webservice\Resources\WebserviceResource\Pages;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Cadastro de webservices (consumidos ou disponibilizados), na base pai e nos clientes.
 */
class WebserviceResource extends Resource
{
    protected static ?string $model = Webservice::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string | \UnitEnum | null $navigationGroup = 'Integrações';

    protected static ?string $navigationLabel = 'Webservices';

    protected static ?string $modelLabel = 'webservice';

    protected static ?string $pluralModelLabel = 'webservices';

    protected static ?string $slug = 'webservices';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Webservice')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')->required()->maxLength(100),

                        Select::make('tipo')
                            ->options(Webservice::TIPOS)
                            ->default('consumir')
                            ->required()
                            ->live()
                            ->native(false),

                        TextInput::make('host')
                            ->label('Host')
                            ->placeholder('https://teste.com')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Só o endereço principal: as rotas de cada serviço entram em Operações.')
                            ->columnSpanFull(),

                        Toggle::make('ativo')->default(true),
                    ]),

                Section::make('Autenticação')
                    ->columns(2)
                    ->schema([
                        Select::make('autenticacao')
                            ->label('Tipo')
                            ->options(Webservice::AUTENTICACOES)
                            ->default('nenhuma')
                            ->required()
                            ->live()
                            ->native(false)
                            ->columnSpanFull(),

                        TextInput::make('credenciais.usuario')->label('Usuário')->visible(fn (Get $get) => $get('autenticacao') === 'basic'),
                        TextInput::make('credenciais.senha')->label('Senha')->password()->revealable()->visible(fn (Get $get) => $get('autenticacao') === 'basic'),

                        TextInput::make('credenciais.token')->label('Token')->password()->revealable()->visible(fn (Get $get) => $get('autenticacao') === 'bearer')->columnSpanFull(),

                        TextInput::make('credenciais.cabecalho')->label('Nome do cabeçalho')->placeholder('X-API-Key')->visible(fn (Get $get) => $get('autenticacao') === 'apikey'),
                        TextInput::make('credenciais.chave')->label('Chave')->password()->revealable()->visible(fn (Get $get) => $get('autenticacao') === 'apikey'),

                        TextInput::make('credenciais.token_url')->label('URL do token')->url()->visible(fn (Get $get) => $get('autenticacao') === 'oauth2' && $get('tipo') !== 'disponibilizar')->columnSpanFull(),
                        Placeholder::make('token_entrada')->label('URL do token')->content('POST /ws/{id}/oauth/token (client_id e client_secret)')->visible(fn (Get $get) => $get('autenticacao') === 'oauth2' && $get('tipo') === 'disponibilizar')->columnSpanFull(),
                        TextInput::make('credenciais.client_id')->label('Client ID')->visible(fn (Get $get) => $get('autenticacao') === 'oauth2'),
                        TextInput::make('credenciais.client_secret')->label('Client secret')->password()->revealable()->visible(fn (Get $get) => $get('autenticacao') === 'oauth2'),
                        TextInput::make('credenciais.scope')->label('Scope')->visible(fn (Get $get) => $get('autenticacao') === 'oauth2')->columnSpanFull(),
                    ]),

                Section::make('Headers padrão')
                    ->description('Enviados em todas as operações deste webservice.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        KeyValue::make('headers')->hiddenLabel()->keyLabel('Header')->valueLabel('Valor'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('tipo')->badge()->formatStateUsing(fn (?string $state) => $state === 'disponibilizar' ? 'Disponibiliza' : 'Consome'),
                TextColumn::make('host')->searchable()->limit(40),
                TextColumn::make('operacoes_count')->counts('operacoes')->label('Operações'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [OperacoesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebservices::route('/'),
            'create' => Pages\CreateWebservice::route('/create'),
            'edit' => Pages\EditWebservice::route('/{record}/edit'),
        ];
    }
}
