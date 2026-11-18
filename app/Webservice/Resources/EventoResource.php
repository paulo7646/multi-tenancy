<?php

namespace App\Webservice\Resources;

use App\Sistema\Abas;
use App\Webservice\Contexto;
use App\Webservice\Models\Evento;
use App\Webservice\RelationManagers\AtributosRelationManager;
use App\Webservice\RelationManagers\BuscaRelationManager;
use App\Webservice\Resources\EventoResource\Pages\EditEvento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Evento de um objeto: tabela de destino, campos de busca e atributos do retorno.
 */
class EventoResource extends ResourceFilho
{
    protected static ?string $model = Evento::class;

    protected static ?string $modelLabel = 'evento';

    protected static ?string $slug = 'webservices/eventos';

    public static function urlPai($record): string
    {
        return ObjetoResource::getUrl('edit', ['record' => $record->objeto_id]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('evento')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Geral')
                            ->icon('heroicon-o-bolt')
                            ->columns(2)
                            ->schema(static::campos()),

                        Abas::filha('Busca', BuscaRelationManager::class, 'heroicon-o-magnifying-glass'),
                        Abas::filha('Atributos', AtributosRelationManager::class, 'heroicon-o-tag'),
                    ]),
            ]);
    }

    /**
     * @return list<Component>
     */
    public static function campos(): array
    {
        return [
            Select::make('tipo')
                ->options(Evento::TIPOS)
                ->required()
                ->live()
                ->native(false),

            Select::make('tabela')
                ->options(fn () => Contexto::tabelas())
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('funcao', null))
                ->native(false)
                ->helperText('Tabela do dicionário onde o evento grava (ou cujo controller tem a função).'),

            Select::make('funcao')
                ->label('Função do controller')
                ->options(fn (Get $get) => Contexto::funcoes($get('tabela')))
                ->required(fn (Get $get) => $get('tipo') === 'codigo_fonte')
                ->visible(fn (Get $get) => $get('tipo') === 'codigo_fonte')
                ->native(false)
                ->helperText('Funções públicas escritas no controller da tabela.')
                ->columnSpanFull(),

            TextInput::make('ordem')->numeric()->default(0),

            Toggle::make('ativo')->default(true)->inline(false),
        ];
    }

    public static function getRelations(): array
    {
        return []; // busca e atributos ficam em abas do form (Abas::filha)
    }

    public static function getPages(): array
    {
        return ['edit' => EditEvento::route('/{record}/edit')];
    }
}
