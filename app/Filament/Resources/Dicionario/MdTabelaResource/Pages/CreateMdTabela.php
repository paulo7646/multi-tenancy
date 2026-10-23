<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\PublicaNoDicionario;
use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Filament\Resources\Dicionario\MdTabelaResource\Pages\Concerns\VerificaMigration;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\Modulo;
use App\Sistema\Enums\TipoTabela;
use App\Sistema\Geradores\Gerador;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMdTabela extends CreateRecord
{
    use PublicaNoDicionario;
    use VerificaMigration;

    protected static string $resource = MdTabelaResource::class;

    protected bool $criarTelas = false;

    protected function beforeCreate(): void
    {
        // tabela de ligação não tem formulário nem tela
        $tipo = TipoTabela::tryFrom((string) (($this->data['tipo'] ?? null) instanceof \BackedEnum ? $this->data['tipo']->value : ($this->data['tipo'] ?? '')));
        $this->criarTelas = (bool) ($this->data['criar_telas'] ?? false) && ($tipo?->temTelas() ?? true);

        // a tabela fica no banco do módulo
        $this->data['banco'] = Modulo::find($this->data['modulo_id'])?->banco?->value;

        $this->verificarMigration(null);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['banco'] = Modulo::findOrFail($data['modulo_id'])->banco->value;

        return $data;
    }

    protected function publicarNoDicionario(Model $record): array
    {
        $resumo = Gerador::publicarTabela($record);

        if ($this->criarTelas) {
            $record->refresh()->load('modulo');

            $formulario = MdFormulario::create([
                'md_tabela_id' => $record->id,
                'nome' => $record->nome,
                'titulo' => $record->nome,
                'nome_fisico' => $record->nome_fisico,
                'nome_arquivo' => 'View' . $record->nomeClasse(),
                'paginado' => false,
                'colunas' => 2,
            ]);
            $formulario->salvarLayout(MdFormulario::layoutPadrao($record));

            $gerenciamento = MdGerenciamento::create([
                'md_tabela_id' => $record->id,
                'md_formulario_id' => $formulario->id,
                'nome' => $record->nome,
                'titulo' => $record->nome,
                'nome_fisico' => $record->nome_fisico,
                'nome_arquivo' => $record->nomeClasse() . 'Resource',
            ]);

            $resumo = [...$resumo, ...Gerador::publicarFormulario($formulario), ...Gerador::publicarGerenciamento($gerenciamento)];
        }

        return $resumo;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
