<?php

namespace App\Plataforma\Resources;

use App\Plataforma\Models\ModeloImpressao;
use App\Plataforma\Resources\ModeloImpressaoResource\Pages\ManageModelosImpressao;
use App\Webservice\Contexto;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Modelos de impressão das tabelas: papel, título, rodapé e, nas fichas, o corpo com {CAMPO}.
 */
class ModeloImpressaoResource extends Resource
{
    protected static ?string $model = ModeloImpressao::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Modelos de impressão';

    protected static ?string $modelLabel = 'modelo de impressão';

    protected static ?string $pluralModelLabel = 'modelos de impressão';

    protected static ?string $slug = 'modelos-de-impressao';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('nome')->required()->maxLength(100),
            Select::make('tipo')->options(ModeloImpressao::TIPOS)->default('registro')->required()->live()->native(false),
            Select::make('tabela')->label('Tabela')->options(fn () => Contexto::tabelas())->searchable()->native(false)
                ->placeholder('Qualquer tabela')->helperText('Em branco, o modelo vale para todas as tabelas.'),
            TextInput::make('titulo')->label('Título')->maxLength(150)->helperText('Em branco, usa o nome da tabela.'),
            Select::make('papel')->options(ModeloImpressao::PAPEIS)->default('a4')->required()->native(false),
            Select::make('orientacao')->label('Orientação')->options(ModeloImpressao::ORIENTACOES)->default('portrait')->required()->native(false),
            Textarea::make('corpo')
                ->label('Corpo da ficha')
                ->rows(10)
                ->visible(fn (Get $get) => $get('tipo') === 'registro')
                ->helperText('HTML simples (p, b, i, table, ul...). Use {NOME_DO_CAMPO} para o valor de um campo, e {EMPRESA}, {DATA}, {HORA} e {USUARIO}. Em branco, imprime todos os campos.')
                ->columnSpanFull(),
            Textarea::make('rodape')->label('Rodapé')->rows(2)->maxLength(500)->columnSpanFull(),
            Toggle::make('mostrar_cabecalho')->label('Mostrar o cabeçalho da empresa')->default(true),
            Toggle::make('padrao')->label('Modelo padrão')->helperText('Usado quando ninguém escolhe outro.'),
            Toggle::make('ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('tipo')->badge()->formatStateUsing(fn (?string $state) => ModeloImpressao::TIPOS[$state] ?? $state),
                TextColumn::make('tabela')->placeholder('Todas'),
                IconColumn::make('padrao')->label('Padrão')->boolean(),
                IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([EditAction::make()->iconButton(), DeleteAction::make()->iconButton()])
            ->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageModelosImpressao::route('/')];
    }
}
