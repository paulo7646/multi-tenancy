<?php

namespace App\Webservice\Resources;

use App\Webservice\Models\Operacao;
use App\Webservice\RelationManagers\ParametrosRelationManager;
use App\Webservice\RelationManagers\VersoesRelationManager;
use App\Webservice\Resources\OperacaoResource\Pages\EditOperacao;
use App\Webservice\Motor\Integrador;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Serviço de um webservice: rota, método e formato, com Parâmetros e Versões (layouts).
 */
class OperacaoResource extends ResourceFilho
{
    protected static ?string $model = Operacao::class;

    protected static ?string $modelLabel = 'operação';

    protected static ?string $slug = 'webservices/operacoes';

    public static function urlPai($record): string
    {
        return WebserviceResource::getUrl('edit', ['record' => $record->webservice_id]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nome')->required()->maxLength(100),

                Select::make('metodo')
                    ->label('Método')
                    ->options(Operacao::METODOS)
                    ->default('GET')
                    ->required()
                    ->native(false),

                TextInput::make('rota')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('/consulta/teste')
                    ->prefix(fn (?Operacao $record) => $record?->webservice?->host)
                    ->helperText('Use {nome} para parâmetros que vão na URL. Ex.: /usuario/{id}')
                    ->columnSpanFull(),

                Select::make('formato')
                    ->label('Formato do retorno')
                    ->options(Operacao::FORMATOS)
                    ->default('json')
                    ->required()
                    ->native(false),

                TextInput::make('timeout')
                    ->label('Timeout (segundos)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(600)
                    ->default(30)
                    ->required(),

                Toggle::make('ativo')->default(true),
            ]);
    }

    /**
     * Botão Executar: chama a URL, guarda o arquivo e processa o retorno com o layout vigente.
     */
    public static function acaoExecutar(): Action
    {
        return Action::make('executar')
            ->label('Executar')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn (Operacao $record) => $record->webservice?->tipo === 'consumir')
            ->modalHeading(fn (Operacao $record) => "Executar {$record->nome}")
            ->modalDescription('Chama a URL da operação e processa o retorno, gerando uma integração.')
            ->modalSubmitActionLabel('Executar')
            ->schema(fn (Operacao $record) => $record->parametros
                ->filter(fn ($p) => blank($p->valor))
                ->map(fn ($p) => TextInput::make("valores.{$p->nome}")->label($p->nome)->required((bool) $p->obrigatorio))
                ->values()
                ->all())
            ->action(function (array $data, Operacao $record) {
                $integracao = Integrador::executar($record, $data['valores'] ?? []);

                $notificacao = Notification::make()
                    ->title('Integração #' . $integracao->getKey() . ': ' . (\App\Webservice\Models\Integracao::STATUS[$integracao->status] ?? $integracao->status))
                    ->body($integracao->erro)
                    ->actions([Action::make('ver')->label('Abrir')->url(IntegracaoResource::getUrl('view', ['record' => $integracao]))]);

                $integracao->status === 'concluida' ? $notificacao->success() : ($integracao->status === 'parcial' ? $notificacao->warning() : $notificacao->danger()->persistent());
                $notificacao->send();
            });
    }

    public static function getRelations(): array
    {
        return [ParametrosRelationManager::class, VersoesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['edit' => EditOperacao::route('/{record}/edit')];
    }
}
