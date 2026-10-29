<?php

namespace App\Webservice\RelationManagers;

use App\Webservice\Models\Versao;
use App\Webservice\Resources\VersaoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VersoesRelationManager extends RelationManager
{
    protected static string $relationship = 'versoes';

    protected static ?string $title = 'Versões';

    protected static ?string $modelLabel = 'versão';

    protected static ?string $pluralModelLabel = 'versões';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('versao')->label('Versão')->required()->maxLength(30)->placeholder('1.0'),
                Toggle::make('ativo')->default(true)->inline(false),
                DatePicker::make('inicio_vigencia')->label('Início da vigência'),
                DatePicker::make('expira_em')->label('Expira em')->afterOrEqual('inicio_vigencia'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('versao')
            ->columns([
                TextColumn::make('versao')->label('Versão')->searchable(),
                TextColumn::make('inicio_vigencia')->label('Início')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('expira_em')->label('Expira em')->date('d/m/Y')->placeholder('sem expiração'),
                IconColumn::make('vigente')->label('Vigente')->boolean()->state(fn (Versao $record) => $record->vigente()),
                TextColumn::make('objetos_count')->counts('objetos')->label('Objetos'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('objetos')
                    ->label('Objetos')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Versao $record) => VersaoResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
