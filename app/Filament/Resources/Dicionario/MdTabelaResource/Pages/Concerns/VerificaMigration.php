<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages\Concerns;

use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use App\Sistema\Geradores\Gerador;
use App\Sistema\Schema\SchemaException;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Antes de gravar a tabela, testa a migration que vai sair (no central ou em
 * cada cliente, numa transação desfeita). Se falhar, nada é gravado.
 */
trait VerificaMigration
{
    protected function verificarMigration(?MdTabela $tabela): void
    {
        $dados = $this->data;

        $tabela ??= new MdTabela;
        $tabela->forceFill([
            'nome_fisico' => $tabela->isGerada() ? $tabela->nome_fisico : $dados['nome_fisico'],
            'banco' => $tabela->isGerada() ? $tabela->banco : Banco::from(MdTabelaResource::valorBanco($dados['banco'])),
        ]);
        $tabela->setRelation('modulo', $tabela->modulo ?? Modulo::find($dados['modulo_id']));

        try {
            Gerador::verificarTabela(
                $tabela,
                array_values($dados['campos'] ?? []),
                (bool) ($dados['controla_situacao'] ?? false),
                ($dados['controla_situacao'] ?? false) ? array_values($dados['situacoes'] ?? []) : [],
            );
        } catch (SchemaException $e) {
            Notification::make()
                ->title('A alteração não pode ser aplicada no banco')
                ->body(new HtmlString(collect($e->problems)
                    ->map(fn (array $lista, string $onde) => '<strong>' . e($onde) . '</strong><br>' . collect($lista)->map(fn ($p) => '• ' . e($p))->join('<br>'))
                    ->join('<br><br>') . '<br><br>Nada foi gravado.'))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
