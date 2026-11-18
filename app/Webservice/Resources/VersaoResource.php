<?php

namespace App\Webservice\Resources;

use App\Sistema\Abas;
use App\Webservice\Models\Versao;
use App\Webservice\RelationManagers\ObjetosRelationManager;
use App\Webservice\Resources\VersaoResource\Pages\EditVersao;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Versão do layout de uma operação, com a árvore de objetos do retorno.
 */
class VersaoResource extends ResourceFilho
{
    protected static ?string $model = Versao::class;

    protected static ?string $modelLabel = 'versão';

    protected static ?string $slug = 'webservices/versoes';

    public static function urlPai($record): string
    {
        return OperacaoResource::getUrl('edit', ['record' => $record->operacao_id]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('versao')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Geral')
                            ->icon('heroicon-o-document-duplicate')
                            ->columns(2)
                            ->schema([
                                TextInput::make('versao')->label('Versão')->required()->maxLength(30)->placeholder('1.0'),

                                Toggle::make('ativo')->default(true)->inline(false),

                                DatePicker::make('inicio_vigencia')->label('Início da vigência'),

                                DatePicker::make('expira_em')
                                    ->label('Expira em')
                                    ->afterOrEqual('inicio_vigencia')
                                    ->helperText('Depois dessa data a versão deixa de ser usada.'),
                            ]),

                        Abas::filha('Objetos', ObjetosRelationManager::class, 'heroicon-o-cube'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return []; // os objetos ficam numa aba do form (Abas::filha)
    }

    public static function getPages(): array
    {
        return ['edit' => EditVersao::route('/{record}/edit')];
    }
}
