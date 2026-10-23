<?php

namespace App\Filament\Resources\Dicionario;

use App\Filament\Resources\Dicionario\Concerns\AcessoDicionario;
use App\Filament\Resources\Dicionario\MdStatusResource\Pages;
use App\Models\Md\MdStatus;
use App\Sistema\Geradores\Exportador;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Situações que as tabelas podem controlar (ícone e cor no datagrid e no formulário).
 */
class MdStatusResource extends Resource
{
    use AcessoDicionario;

    protected static ?string $model = MdStatus::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $modelLabel = 'Situação';

    protected static ?string $pluralModelLabel = 'Situações';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'dicionario/situacoes';

    public static function form(Schema $schema): Schema
    {
        // o código fica gravado nos registros: depois de usado numa tabela, não muda
        $emUso = fn (?MdStatus $record) => (bool) $record?->tabelas()->exists();

        return $schema->components([
            Section::make('Situação')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('nome')
                        ->required()
                        ->maxLength(60)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?MdStatus $record) {
                            if (! $record && blank($get('nome_fisico'))) {
                                $set('nome_fisico', Campos::snake($state));
                            }
                        }),

                    Campos::nomeFisico(max: 40)
                        ->label('Código')
                        ->unique(ignoreRecord: true)
                        ->helperText('Valor gravado na coluna situacao dos registros.')
                        ->disabled($emUso),

                    Campos::icone(),

                    Campos::cor(padrao: 'gray')->label('Cor do ícone'),

                    TextInput::make('descricao')
                        ->label('Descrição')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                IconColumn::make('icone')
                    ->label('')
                    ->icon(fn (MdStatus $record) => $record->icone ?: 'heroicon-o-flag')
                    ->color(fn (MdStatus $record) => $record->corFilament()),
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('nome_fisico')->label('Código')->badge()->color(fn (MdStatus $record) => $record->corFilament()),
                TextColumn::make('tabelas_count')->counts('tabelas')->label('Tabelas'),
            ])
            ->recordActions([
                EditAction::make(),
                static::excluir(DeleteAction::make()),
            ]);
    }

    public static function excluir(DeleteAction $action): DeleteAction
    {
        return $action
            ->before(function (DeleteAction $action, MdStatus $record) {
                if ($record->tabelas()->exists()) {
                    Notification::make()->title('Situação em uso')->body('Tire esta situação das tabelas antes de excluir.')->danger()->send();
                    $action->cancel();
                }
            })
            ->after(fn () => Exportador::status());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMdStatus::route('/'),
            'create' => Pages\CreateMdStatus::route('/create'),
            'edit' => Pages\EditMdStatus::route('/{record}/edit'),
        ];
    }
}
