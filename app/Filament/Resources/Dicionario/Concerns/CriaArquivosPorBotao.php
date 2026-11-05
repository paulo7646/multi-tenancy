<?php

namespace App\Filament\Resources\Dicionario\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Telas de edição do dicionário (tabela, formulário, gerenciamento, módulo): salvar
 * não gera nada; o botão Criar arquivos pergunta se está tudo certo, mostra o que
 * vai ser feito e só então gera (migrations, model, classes, arquivos do dicionário).
 */
trait CriaArquivosPorBotao
{
    use SalvaNoDicionario;

    /**
     * Gera os arquivos.
     *
     * @return list<string> resumo do que foi gerado
     */
    abstract protected function publicarNoDicionario(Model $record): array;

    /**
     * O que a geração vai fazer, para a pergunta de confirmação.
     *
     * @return list<string>
     */
    abstract protected function previaDosArquivos(Model $record): array;

    /**
     * Motivo para não gerar agora (ex.: a tabela ainda não tem arquivos), ou null.
     */
    protected function motivoParaNaoCriarArquivos(Model $record): ?string
    {
        return null;
    }

    /**
     * Testes antes de gerar (ex.: a migration roda numa transação desfeita).
     * Quem recusa avisa o usuário e devolve false.
     */
    protected function verificarAntesDeCriar(Model $record): bool
    {
        return true;
    }

    protected function acaoCriarArquivos(): Action
    {
        return Action::make('criarArquivos')
            ->label('Criar arquivos')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Está tudo certo? Criar os arquivos agora?')
            ->modalDescription(fn () => $this->descricaoDosArquivos())
            ->modalSubmitActionLabel('Sim, criar os arquivos')
            ->modalCancelActionLabel('Voltar')
            ->action(fn () => $this->criarArquivos());
    }

    public function descricaoDosArquivos(): HtmlString
    {
        $record = $this->getRecord()->fresh();

        if ($motivo = $this->motivoParaNaoCriarArquivos($record)) {
            return new HtmlString('<strong>'.e($motivo).'</strong>');
        }

        $itens = collect($this->previaDosArquivos($record))->map(fn (string $linha) => '<li>'.e($linha).'</li>')->join('');

        return new HtmlString(
            '<p>Vai ser feito a partir do que está <strong>salvo</strong> (salve as alterações antes):</p>'
            .'<ul style="list-style: disc; padding-left: 1.25rem; margin: .5rem 0">'.$itens.'</ul>'
        );
    }

    public function criarArquivos(): void
    {
        $record = $this->getRecord()->fresh();

        if ($motivo = $this->motivoParaNaoCriarArquivos($record)) {
            Notification::make()->title('Ainda não dá para criar os arquivos')->body($motivo)->warning()->send();

            return;
        }

        if (! $this->verificarAntesDeCriar($record)) {
            return;
        }

        try {
            $resumo = $this->publicarNoDicionario($record);

            Notification::make()
                ->title('Arquivos criados')
                ->body(new HtmlString(implode('<br>', array_map('e', $resumo))))
                ->success()
                ->send();

        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('A geração dos arquivos falhou')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
