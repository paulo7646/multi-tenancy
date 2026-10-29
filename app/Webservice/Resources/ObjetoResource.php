<?php

namespace App\Webservice\Resources;

use App\Webservice\Models\Objeto;
use App\Webservice\RelationManagers\EventosRelationManager;
use App\Webservice\Resources\ObjetoResource\Pages\EditObjeto;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

/**
 * Objeto do retorno (nó da árvore), com os eventos que rodam quando ele é processado.
 */
class ObjetoResource extends ResourceFilho
{
    protected static ?string $model = Objeto::class;

    protected static ?string $modelLabel = 'objeto';

    protected static ?string $slug = 'webservices/objetos';

    public static function urlPai($record): string
    {
        return VersaoResource::getUrl('edit', ['record' => $record->versao_id]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(static::campos());
    }

    /**
     * Campos do objeto (também usados no cadastro rápido da versão).
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function campos(?int $versaoId = null, ?int $exceto = null): array
    {
        return [
            TextInput::make('nome')
                ->required()
                ->maxLength(100)
                ->helperText('Raiz: root (o {} principal). Os demais, exatamente o nome que vem no retorno.'),

            Select::make('pai_id')
                ->label('Objeto pai')
                ->options(fn (?Objeto $record) => Objeto::query()
                    ->where('versao_id', $versaoId ?? $record?->versao_id)
                    ->when($exceto ?? $record?->getKey(), fn ($q, $id) => $q->whereKeyNot($id))
                    ->orderBy('ordem')->orderBy('id')
                    ->pluck('nome', 'id')
                    ->all())
                ->placeholder('Nenhum (raiz)')
                ->native(false),

            Toggle::make('lista')
                ->label('É uma lista')
                ->helperText('O retorno traz vários itens deste objeto: os eventos rodam para cada um.'),

            Toggle::make('obrigatorio')
                ->label('Obrigatório')
                ->default(true)
                ->helperText('Se não vier no retorno, a integração acusa erro.'),

            Toggle::make('novo_lote')
                ->label('Gera novo lote')
                ->helperText('Cada item deste objeto abre um lote próprio (uma transação).'),

            TextInput::make('ordem')->numeric()->default(0),
        ];
    }

    public static function getRelations(): array
    {
        return [EventosRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['edit' => EditObjeto::route('/{record}/edit')];
    }
}
