<?php

namespace App\Webservice\Resources;

use App\Webservice\Contexto;
use App\Webservice\Models\Agendamento;
use App\Webservice\Models\Operacao;
use App\Webservice\Motor\Agendador;
use App\Webservice\Resources\AgendamentoResource\Pages;
use Cron\CronExpression;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * Agendamentos: rodam uma função do controller de uma tabela ou um layout de EDI no tempo.
 */
class AgendamentoResource extends Resource
{
    protected static ?string $model = Agendamento::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | \UnitEnum | null $navigationGroup = 'Integrações';

    protected static ?string $navigationLabel = 'Agendamentos';

    protected static ?string $modelLabel = 'agendamento';

    protected static ?string $slug = 'webservices/agendamentos';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Agendamento')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')->required()->maxLength(100),

                        Select::make('tipo')
                            ->options(Agendamento::TIPOS)
                            ->default('layout_edi')
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('tabela')
                            ->options(fn () => Contexto::tabelas())
                            ->required(fn (Get $get) => $get('tipo') === 'codigo_fonte')
                            ->visible(fn (Get $get) => $get('tipo') === 'codigo_fonte')
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('funcao', null))
                            ->native(false),

                        Select::make('funcao')
                            ->label('Função do controller')
                            ->options(fn (Get $get) => Contexto::funcoes($get('tabela')))
                            ->required(fn (Get $get) => $get('tipo') === 'codigo_fonte')
                            ->visible(fn (Get $get) => $get('tipo') === 'codigo_fonte')
                            ->native(false)
                            ->helperText('A função recebe o agendamento como parâmetro.'),

                        Select::make('operacao_id')
                            ->label('Operação (layout de EDI)')
                            ->options(fn () => Operacao::with('webservice')->get()->mapWithKeys(fn (Operacao $o) => [$o->getKey() => "{$o->webservice?->nome} › {$o->nome}"])->all())
                            ->required(fn (Get $get) => $get('tipo') === 'layout_edi')
                            ->visible(fn (Get $get) => $get('tipo') === 'layout_edi')
                            ->searchable()
                            ->native(false)
                            ->helperText('Roda o layout vigente da operação: busca a URL e processa o retorno.')
                            ->columnSpanFull(),

                        KeyValue::make('parametros')
                            ->label('Valores dos parâmetros')
                            ->keyLabel('Parâmetro')
                            ->valueLabel('Valor')
                            ->addActionLabel('Adicionar valor')
                            ->visible(fn (Get $get) => $get('tipo') === 'layout_edi')
                            ->helperText('Parâmetros da operação sem valor fixo e obrigatórios precisam de valor aqui, pois ninguém digita na execução agendada.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Quando')
                    ->columns(2)
                    ->schema([
                        Select::make('frequencia')
                            ->label('Frequência')
                            ->options(Agendamento::FREQUENCIAS)
                            ->default('minutos')
                            ->required()
                            ->live()
                            ->native(false),

                        TextInput::make('intervalo')
                            ->label(fn (Get $get) => $get('frequencia') === 'horas' ? 'A cada quantas horas' : 'A cada quantos minutos')
                            ->numeric()->minValue(1)->maxValue(1440)->default(15)
                            ->required(fn (Get $get) => in_array($get('frequencia'), ['minutos', 'horas'], true))
                            ->visible(fn (Get $get) => in_array($get('frequencia'), ['minutos', 'horas'], true)),

                        TextInput::make('hora')
                            ->label('Hora')->placeholder('08:30')
                            ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                            ->required(fn (Get $get) => in_array($get('frequencia'), ['diario', 'semanal'], true))
                            ->visible(fn (Get $get) => in_array($get('frequencia'), ['diario', 'semanal'], true)),

                        Select::make('dia_semana')
                            ->label('Dia da semana')
                            ->options(Agendamento::DIAS)
                            ->required(fn (Get $get) => $get('frequencia') === 'semanal')
                            ->visible(fn (Get $get) => $get('frequencia') === 'semanal')
                            ->native(false),

                        TextInput::make('cron')
                            ->label('Expressão cron')
                            ->placeholder('*/15 * * * *')
                            ->required(fn (Get $get) => $get('frequencia') === 'cron')
                            ->visible(fn (Get $get) => $get('frequencia') === 'cron')
                            ->rule(fn () => fn (string $attribute, mixed $value, \Closure $fail) => CronExpression::isValidExpression((string) $value) ?: $fail('Expressão cron inválida.'))
                            ->columnSpanFull(),

                        DateTimePicker::make('expira_em')
                            ->label('Expira em')
                            ->helperText('Depois dessa data o agendamento para de rodar.'),

                        Toggle::make('ativo')->default(true)->inline(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('tipo')->badge()->formatStateUsing(fn (?string $state) => $state === 'layout_edi' ? 'Layout de EDI' : 'Código-fonte'),
                TextColumn::make('cron')->label('Cron')->fontFamily('mono'),
                TextColumn::make('proxima_execucao')->label('Próxima')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('ultima_execucao')->label('Última')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('ultimo_status')->label('Resultado')->badge()
                    ->color(fn (?string $state) => $state === 'ok' ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state) => $state === 'ok' ? 'OK' : ($state === 'erro' ? 'Erro' : '—'))
                    ->tooltip(fn (Agendamento $record) => $record->ultimo_erro),
                IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([
                Action::make('executar')
                    ->label('Executar agora')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function (Agendamento $record) {
                        Agendador::executar($record);
                        $record->refresh();

                        $record->ultimo_status === 'ok'
                            ? Notification::make()->title('Agendamento executado')->success()->send()
                            : Notification::make()->title('O agendamento falhou')->body($record->ultimo_erro)->danger()->persistent()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgendamentos::route('/'),
            'create' => Pages\CreateAgendamento::route('/create'),
            'edit' => Pages\EditAgendamento::route('/{record}/edit'),
        ];
    }
}
