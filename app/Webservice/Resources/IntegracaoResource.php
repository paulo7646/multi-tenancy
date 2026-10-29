<?php

namespace App\Webservice\Resources;

use App\Webservice\Models\Integracao;
use App\Webservice\RelationManagers\LotesRelationManager;
use App\Webservice\Resources\IntegracaoResource\Pages;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * Histórico das integrações: cada execução de um layout, com o arquivo e os lotes.
 */
class IntegracaoResource extends Resource
{
    protected static ?string $model = Integracao::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string | \UnitEnum | null $navigationGroup = 'Integrações';

    protected static ?string $navigationLabel = 'Integrações';

    protected static ?string $modelLabel = 'integração';

    protected static ?string $slug = 'webservices/integracoes';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Integração')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('operacao.nome')->label('Operação')->placeholder('(operação excluída)'),
                        TextEntry::make('versao.versao')->label('Versão do layout')->placeholder('—'),
                        TextEntry::make('origem')->badge()->formatStateUsing(fn (?string $state) => Integracao::ORIGENS[$state] ?? $state),
                        TextEntry::make('status')->badge()
                            ->color(fn (?string $state) => static::cor($state))
                            ->formatStateUsing(fn (?string $state) => Integracao::STATUS[$state] ?? $state),
                        TextEntry::make('iniciada_em')->label('Iniciada')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                        TextEntry::make('finalizada_em')->label('Finalizada')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                        TextEntry::make('erro')->label('Erro')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Arquivo')
                    ->description(fn (Integracao $record) => $record->arquivo)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('conteudo')
                            ->hiddenLabel()
                            ->state(fn (Integracao $record) => $record->conteudo() === null ? null : str($record->conteudo())->limit(20000)->toString())
                            ->placeholder('Sem arquivo (a execução falhou antes de receber o conteúdo).')
                            ->fontFamily(FontFamily::Mono)
                            ->extraAttributes(['style' => 'white-space: pre-wrap; word-break: break-all']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('operacao.nome')->label('Operação')->placeholder('(excluída)')->searchable(),
                TextColumn::make('origem')->badge()->formatStateUsing(fn (?string $state) => Integracao::ORIGENS[$state] ?? $state),
                TextColumn::make('status')->badge()
                    ->color(fn (?string $state) => static::cor($state))
                    ->formatStateUsing(fn (?string $state) => Integracao::STATUS[$state] ?? $state),
                TextColumn::make('lotes_total')->label('Lotes'),
                TextColumn::make('lotes_erro')->label('Com erro')->color(fn ($state) => $state > 0 ? 'danger' : null),
                TextColumn::make('iniciada_em')->label('Iniciada')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Integracao::STATUS),
                SelectFilter::make('origem')->options(Integracao::ORIGENS),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('baixar')
                    ->label('Arquivo')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Integracao $record) => filled($record->arquivo) && Storage::disk('local')->exists($record->arquivo))
                    ->action(fn (Integracao $record) => Storage::disk('local')->download($record->arquivo)),
            ]);
    }

    protected static function cor(?string $status): string
    {
        return match ($status) {
            'concluida' => 'success',
            'parcial' => 'warning',
            'erro' => 'danger',
            default => 'gray',
        };
    }

    public static function getRelations(): array
    {
        return [LotesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegracoes::route('/'),
            'view' => Pages\ViewIntegracao::route('/{record}'),
        ];
    }
}
