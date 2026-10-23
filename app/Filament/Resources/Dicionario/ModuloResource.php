<?php

namespace App\Filament\Resources\Dicionario;

use App\Filament\Resources\Dicionario\Concerns\AcessoDicionario;
use App\Filament\Resources\Dicionario\ModuloResource\Pages;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Geradores\Gerador;
use App\Sistema\Permissoes;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Throwable;

/**
 * Módulos do sistema: viram grupos no menu lateral e dão o prefixo às
 * tabelas (op_usuario = tabela usuario do módulo Operacional).
 */
class ModuloResource extends Resource
{
    use AcessoDicionario;

    protected static ?string $model = Modulo::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $modelLabel = 'Módulo';

    protected static ?string $pluralModelLabel = 'Módulos';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'dicionario/modulos';

    public static function form(Schema $schema): Schema
    {
        $temTabelas = fn (?Modulo $record) => (bool) $record?->tabelas()->exists();

        return $schema->components([
            Section::make('Módulo')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('nome')
                        ->required()
                        ->maxLength(60)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Modulo $record) {
                            if (! $record && blank($get('pasta')) && filled($state)) {
                                $set('pasta', Str::studly(Str::ascii($state)));
                            }
                        }),

                    TextInput::make('prefixo')
                        ->required()
                        ->maxLength(5)
                        ->regex('/^[a-z]{2,5}$/')
                        ->validationMessages(['regex' => 'De 2 a 5 letras minúsculas (ex.: op).'])
                        ->unique(ignoreRecord: true)
                        ->helperText('Início do nome físico das tabelas: op_usuario.')
                        ->disabled($temTabelas),

                    TextInput::make('pasta')
                        ->required()
                        ->maxLength(60)
                        ->regex('/^[A-Z][A-Za-z0-9]*$/')
                        ->validationMessages(['regex' => 'Começa com maiúscula, só letras e números (ex.: Operacional).'])
                        ->unique(ignoreRecord: true)
                        ->prefix('app/Modulos/')
                        ->disabledOn('edit'),

                    ToggleButtons::make('banco')
                        ->label('Banco')
                        ->options(Banco::class)
                        ->default(Banco::Tenant)
                        ->required()
                        ->inline()
                        ->helperText('As tabelas do módulo ficam neste banco, e o módulo aparece no menu do painel dele. Fica fixo depois que o módulo tiver tabelas.')
                        ->disabled($temTabelas)
                        ->columnSpan(2),


                    TextInput::make('ordem')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->helperText('Posição do módulo no menu.'),

                    Select::make('roles')
                        ->label('Papéis com acesso')
                        ->multiple()
                        ->options(fn () => Permissoes::sugestoesDePapeis())
                        ->helperText('O super_admin sempre acessa. Permissão modulo_{prefixo}, criada no banco do módulo (no central ou em todos os clientes).'),

                    Textarea::make('descricao')
                        ->label('Descrição')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('prefixo')->badge()->searchable(),
                TextColumn::make('banco')->badge(),
                TextColumn::make('pasta')->prefix('app/Modulos/')->color('gray'),
                TextColumn::make('tabelas_count')->counts('tabelas')->label('Tabelas'),
                TextColumn::make('roles')->label('Papéis')->badge()->placeholder('só super_admin'),
                TextColumn::make('ordem')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                static::excluir(DeleteAction::make()),
            ]);
    }

    public static function excluir(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, Modulo $record) {
            try {
                Gerador::excluirModulo($record);
            } catch (Throwable $e) {
                Notification::make()->title('Não foi possível excluir')->body($e->getMessage())->danger()->send();
                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModulos::route('/'),
            'create' => Pages\CreateModulo::route('/create'),
            'edit' => Pages\EditModulo::route('/{record}/edit'),
        ];
    }
}
