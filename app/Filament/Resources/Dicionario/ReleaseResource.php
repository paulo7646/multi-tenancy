<?php

namespace App\Filament\Resources\Dicionario;

use App\Filament\Resources\Dicionario\ReleaseResource\Pages\ManageReleases;
use App\Models\Md\MdRelease;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Permissoes;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Gerador de pacotes: publica o que mudou em cada módulo de cliente (dicionário e migrations). Cada
 * empresa vê e aplica, na aba Releases das configurações, só as releases dos módulos que tem.
 * Funciona também em produção (as migrations chegam pelo deploy), só para super_admin.
 */
class ReleaseResource extends Resource
{
    protected static ?string $model = MdRelease::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?string $modelLabel = 'Pacote';

    protected static ?string $pluralModelLabel = 'Gerador de pacotes';

    protected static ?string $navigationLabel = 'Gerador de pacotes';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'dicionario/pacotes';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(Permissoes::superAdmin());
    }

    public static function getNavigationGroup(): string
    {
        return 'Dicionário de dados';
    }

    public static function canEdit(Model $record): bool
    {
        return false; // uma release publicada não muda: publica-se uma nova
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('modulo_id')
                ->label('Módulo')
                ->options(fn () => Modulo::query()->where('banco', Banco::Tenant->value)->orderBy('nome')->pluck('nome', 'id')->all())
                ->required()
                ->searchable()
                ->live(),

            Placeholder::make('resumo')
                ->label('O que vai no pacote')
                ->content(function (Get $get): string {
                    $modulo = Modulo::find($get('modulo_id'));

                    if (! $modulo) {
                        return 'Escolha o módulo.';
                    }

                    // só contagens: montar o pacote inteiro a cada interação do formulário custaria milhares de consultas
                    $tabelas = MdTabela::where('modulo_id', $modulo->id)->count();
                    $pasta = Banco::Tenant->migrationsPath($modulo);
                    $migrations = is_dir($pasta) ? count(glob($pasta.'/*.php') ?: []) : 0;
                    $ultima = MdRelease::where('modulo_id', $modulo->id)->max('versao');

                    return "{$tabelas} tabela(s) e {$migrations} migration(s). "
                        .($ultima ? "Última release: v{$ultima}." : 'Ainda sem release.');
                }),

            Textarea::make('notas')->label('Notas da release')->rows(3)->helperText('O que mudou: aparece para os clientes na aba Releases.'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('pacote')->tabs([
                Tab::make('Geral')->icon('heroicon-o-archive-box')->columns(2)->schema([
                    TextEntry::make('modulo.nome')->label('Módulo'),
                    TextEntry::make('versao')->label('Versão')->prefix('v'),
                    TextEntry::make('publicado_em')->label('Publicado em')->dateTime('d/m/Y H:i'),
                    TextEntry::make('publicado_por')->label('Publicado por')->placeholder('—'),
                    TextEntry::make('notas')->label('Notas')->placeholder('—')->columnSpanFull(),
                ]),
                Tab::make('Migrations')->icon('heroicon-o-circle-stack')->schema([
                    TextEntry::make('migrations')
                        ->hiddenLabel()
                        ->state(fn (MdRelease $record) => collect($record->migrations())->pluck('arquivo')->all())
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->placeholder('Nenhuma migration.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('publicado_em', 'desc')
            ->columns([
                TextColumn::make('modulo.nome')->label('Módulo')->sortable()->searchable(),
                TextColumn::make('versao')->label('Versão')->prefix('v')->sortable(),
                TextColumn::make('notas')->limit(60)->placeholder('—'),
                TextColumn::make('migrations_total')->label('Migrations')->state(fn (MdRelease $r) => count($r->migrations()))->badge(),
                TextColumn::make('publicado_em')->label('Publicado em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('publicado_por')->label('Por')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReleases::route('/')];
    }
}
