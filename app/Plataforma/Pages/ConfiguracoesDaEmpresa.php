<?php

namespace App\Plataforma\Pages;

use App\Plataforma\Models\Configuracao;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Dados da empresa usados no cabeçalho das impressões e nos certificados de assinatura.
 */
class ConfiguracoesDaEmpresa extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Dados da empresa';

    protected static ?string $title = 'Dados da empresa';

    protected static ?string $slug = 'dados-da-empresa';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    public function getSubheading(): string
    {
        return 'Aparecem no cabeçalho de PDFs, fichas e certificados de assinatura.';
    }

    public function mount(): void
    {
        $this->form->fill(Configuracao::empresa());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Empresa')->columns(2)->schema([
                    TextInput::make('nome')->label('Nome')->required()->maxLength(150),
                    TextInput::make('documento')->label('CNPJ ou documento')->maxLength(30),
                    TextInput::make('endereco')->label('Endereço')->maxLength(255)->columnSpanFull(),
                    TextInput::make('contato')->label('Contato (telefone, e-mail, site)')->maxLength(255)->columnSpanFull(),
                    Textarea::make('rodape')->label('Rodapé das impressões')->rows(2)->maxLength(500)->columnSpanFull(),
                    FileUpload::make('logo')
                        ->label('Logo')
                        ->image()
                        ->disk('local')
                        ->directory('plataforma')
                        ->visibility('private')
                        ->maxSize(1024)
                        ->helperText('PNG ou JPG, até 1 MB.')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('salvar')
                ->footer([
                    Actions::make([
                        Action::make('salvar')->label('Salvar')->submit('salvar')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();

        foreach (['nome', 'documento', 'endereco', 'contato', 'rodape', 'logo'] as $chave) {
            Configuracao::definir("empresa.{$chave}", $dados[$chave] ?? null);
        }

        Notification::make()->title('Dados da empresa salvos')->success()->send();
    }
}
