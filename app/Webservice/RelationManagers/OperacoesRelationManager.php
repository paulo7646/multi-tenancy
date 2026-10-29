<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Models\Operacao;
use App\Webservice\Resources\OperacaoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperacoesRelationManager extends RelationManager
{
    protected static string $relationship = 'operacoes';

    protected static ?string $title = 'Operações';

    protected static ?string $modelLabel = 'operação';

    protected static ?string $pluralModelLabel = 'operações';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nome')->required()->maxLength(100),
                Select::make('metodo')->label('Método')->options(Operacao::METODOS)->default('GET')->required()->native(false),
                TextInput::make('rota')->required()->maxLength(255)->placeholder('/consulta/teste')->columnSpanFull(),
                Select::make('formato')->label('Formato do retorno')->options(Operacao::FORMATOS)->default('json')->required()->native(false),
                TextInput::make('timeout')->label('Timeout (segundos)')->numeric()->default(30)->required(),
                Toggle::make('ativo')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('metodo')->label('Método')->badge(),
                TextColumn::make('rota')->searchable(),
                TextColumn::make('formato')->formatStateUsing(fn (?string $state) => strtoupper((string) $state)),
                IconColumn::make('ativo')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Operacao $record) => OperacaoResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
