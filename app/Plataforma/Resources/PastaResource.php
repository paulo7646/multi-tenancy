<?php

namespace App\Plataforma\Resources;

use App\Plataforma\Models\Pasta;
use App\Plataforma\Resources\PastaResource\Pages\ManagePastas;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\QueryException;

/**
 * Pastas dos documentos (podem ter subpastas).
 */
class PastaResource extends Resource
{
    protected static ?string $model = Pasta::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentos';

    protected static ?string $navigationLabel = 'Pastas';

    protected static ?string $modelLabel = 'pasta';

    protected static ?string $pluralModelLabel = 'pastas';

    protected static ?string $slug = 'pastas';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')->required()->maxLength(100),
            Select::make('pai_id')
                ->label('Dentro de')
                ->options(fn (?Pasta $record) => Pasta::opcoes($record?->getKey()))
                ->searchable()
                ->native(false)
                ->placeholder('Raiz'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('pai.pai.pai')->withCount('arquivos'))
            ->columns([
                TextColumn::make('caminho')->label('Pasta')->state(fn (Pasta $record) => $record->caminho())->weight('bold'),
                TextColumn::make('arquivos_count')->label('Documentos'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()
                    ->using(function (Pasta $record) {
                        try {
                            return $record->delete();
                        } catch (QueryException) {
                            Notification::make()->title('A pasta tem subpastas')->body('Mova ou apague as subpastas antes.')->danger()->send();

                            return false;
                        }
                    })
                    ->modalDescription('Os documentos da pasta ficam sem pasta.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePastas::route('/')];
    }
}
