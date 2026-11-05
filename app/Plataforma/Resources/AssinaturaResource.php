<?php

namespace App\Plataforma\Resources;

use App\Plataforma\Assinatura\AssinaturaException;
use App\Plataforma\Assinatura\Assinaturas;
use App\Plataforma\Models\Arquivo;
use App\Plataforma\Models\Assinatura;
use App\Plataforma\Models\Versao;
use App\Plataforma\RelationManagers\AssinantesRelationManager;
use App\Plataforma\RelationManagers\EventosDaAssinaturaRelationManager;
use App\Plataforma\Resources\AssinaturaResource\Pages;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Pedidos de assinatura eletrônica: acompanhamento, convites e certificado.
 */
class AssinaturaResource extends Resource
{
    protected static ?string $model = Assinatura::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentos';

    protected static ?string $navigationLabel = 'Assinaturas';

    protected static ?string $modelLabel = 'assinatura';

    protected static ?string $pluralModelLabel = 'assinaturas';

    protected static ?string $slug = 'assinaturas';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function canCreate(): bool
    {
        return false; // o pedido nasce pelo botão "Novo pedido" ou "Enviar para assinatura" do documento
    }

    /**
     * @return list<Component>
     */
    public static function camposDoPedido(bool $comDocumento = true): array
    {
        return [
            ...($comDocumento ? [
                Select::make('arquivo_id')
                    ->label('Documento (PDF)')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $busca) => Arquivo::where('nome', 'ilike', "%{$busca}%")
                        ->whereHas('versaoAtual', fn ($v) => $v->where('mime', 'application/pdf'))
                        ->limit(30)->pluck('nome', 'id')->all())
                    ->getOptionLabelUsing(fn ($valor) => Arquivo::find($valor)?->nome)
                    ->columnSpanFull(),
            ] : []),

            TextInput::make('titulo')->label('Título do pedido')->required()->maxLength(150)->columnSpanFull(),

            Repeater::make('assinantes')
                ->label('Assinantes')
                ->required()
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel('Adicionar assinante')
                ->columns(2)
                ->columnSpanFull()
                ->itemLabel(fn (array $state) => $state['nome'] ?? 'Assinante')
                ->schema([
                    TextInput::make('nome')->label('Nome completo')->required()->maxLength(150)
                        ->helperText('O assinante digita este nome para confirmar.'),
                    TextInput::make('email')->label('E-mail')->email()->required()->maxLength(150),
                    TextInput::make('identificacao')->label('CPF ou CNPJ')->maxLength(30),
                    TextInput::make('papel')->label('Papel')->placeholder('Contratante, Testemunha...')->maxLength(60),
                ]),

            Toggle::make('ordem_obrigatoria')->label('Assinar na ordem da lista')->default(true)
                ->helperText('Cada pessoa só recebe o convite depois que a anterior assinou.'),
            Toggle::make('exigir_codigo')->label('Exigir código por e-mail')->default(true)
                ->helperText('Segunda confirmação: o assinante digita um código de 6 dígitos enviado ao e-mail dele.'),
            DateTimePicker::make('expira_em')->label('Prazo para assinar')->minDate(now()),
            Toggle::make('enviar_agora')->label('Enviar os convites agora')->default(true)->dehydrated(false),
        ];
    }

    /**
     * Cria o pedido (e envia, se pedido) a partir do formulário.
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws AssinaturaException
     */
    public static function criarPedido(array $dados, Versao $versao): Assinatura
    {
        $pedido = Assinaturas::criar([
            'titulo' => $dados['titulo'],
            'ordem_obrigatoria' => (bool) ($dados['ordem_obrigatoria'] ?? true),
            'exigir_codigo' => (bool) ($dados['exigir_codigo'] ?? true),
            'expira_em' => $dados['expira_em'] ?? null,
        ], $versao, array_values($dados['assinantes'] ?? []));

        if ($dados['enviar_agora'] ?? true) {
            Assinaturas::enviar($pedido);
        }

        return $pedido->refresh();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Pedido')->columns(3)->schema([
                TextEntry::make('titulo')->columnSpanFull(),
                TextEntry::make('status')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'concluido' => 'success', 'cancelado', 'expirado' => 'danger', 'em_andamento' => 'warning', default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => Assinatura::STATUS[$state] ?? $state),
                TextEntry::make('versao.nome_original')->label('Arquivo'),
                TextEntry::make('expira_em')->label('Prazo')->dateTime('d/m/Y H:i')->placeholder('sem prazo'),
                TextEntry::make('codigo_verificacao')->label('Código de verificação')->copyable(),
                TextEntry::make('hash_documento')->label('SHA-256 do arquivo')->limit(24)->tooltip(fn (Assinatura $record) => $record->hash_documento),
                TextEntry::make('criado_por')->label('Criado por')->placeholder('—'),
                TextEntry::make('motivo_cancelamento')->label('Motivo do cancelamento')->placeholder('—')->columnSpanFull()
                    ->visible(fn (Assinatura $record) => $record->status === 'cancelado'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('titulo')->searchable()->weight('bold'),
                TextColumn::make('status')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'concluido' => 'success', 'cancelado', 'expirado' => 'danger', 'em_andamento' => 'warning', default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => Assinatura::STATUS[$state] ?? $state),
                TextColumn::make('assinantes_resumo')->label('Assinaturas')
                    ->state(fn (Assinatura $record) => $record->assinantes->where('status', 'assinou')->count().' de '.$record->assinantes->count()),
                TextColumn::make('expira_em')->label('Prazo')->dateTime('d/m/Y')->placeholder('—'),
                TextColumn::make('criado_por')->label('Criado por')->toggleable(),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('assinantes'))
            ->filters([SelectFilter::make('status')->options(Assinatura::STATUS)])
            ->recordUrl(fn (Assinatura $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [AssinantesRelationManager::class, EventosDaAssinaturaRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssinaturas::route('/'),
            'view' => Pages\ViewAssinatura::route('/{record}'),
        ];
    }
}
