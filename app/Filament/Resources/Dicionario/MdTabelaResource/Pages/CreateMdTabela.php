<?php

namespace App\Filament\Resources\Dicionario\MdTabelaResource\Pages;

use App\Filament\Resources\Dicionario\Concerns\SalvaNoDicionario;
use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\Modulo;
use App\Sistema\Enums\TipoTabela;
use App\Sistema\Geradores\Gerador;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMdTabela extends CreateRecord
{
    use SalvaNoDicionario;

    protected static string $resource = MdTabelaResource::class;

    protected bool $criarTelas = false;

    public function mount(): void
    {
        parent::mount();

        // vindo da aba Tabelas de um módulo: já escolhe o módulo
        if ($moduloId = request()->integer('modulo_id')) {
            $this->data['modulo_id'] = $moduloId;
        }
    }

    protected function beforeCreate(): void
    {
        // tabela de ligação que exibe o filho não tem formulário nem tela
        $tipo = TipoTabela::tryFrom((string) (($this->data['tipo'] ?? null) instanceof \BackedEnum ? $this->data['tipo']->value : ($this->data['tipo'] ?? '')));
        $temTelas = $tipo === TipoTabela::Ligacao ? ! (bool) ($this->data['exibe_filho'] ?? true) : ($tipo?->temTelas() ?? true);
        $this->criarTelas = (bool) ($this->data['criar_telas'] ?? false) && $temTelas;
        // a tabela fica no banco do módulo
        $this->data['banco'] = Modulo::find($this->data['modulo_id'])?->banco?->value;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['banco'] = Modulo::findOrFail($data['modulo_id'])->banco->value;

        return $data;
    }

    /**
     * Só o cadastro: os campos do sistema e, se pedido, o formulário e a tela padrão.
     * Os arquivos saem no botão Criar arquivos de cada um.
     */
    protected function depoisDeSalvar(Model $record): void
    {
        Gerador::garantirCamposDoSistema($record);

        if (! $this->criarTelas) {
            return;
        }

        $record->refresh()->load('modulo');

        $formulario = MdFormulario::create([
            'md_tabela_id' => $record->id,
            'nome' => $record->nome,
            'titulo' => $record->nome,
            'nome_fisico' => $record->nome_fisico,
            'nome_arquivo' => 'View'.$record->nomeClasse(),
            'paginado' => false,
            'colunas' => 2,
        ]);
        $formulario->salvarLayout(MdFormulario::layoutPadrao($record));

        MdGerenciamento::create([
            'md_tabela_id' => $record->id,
            'md_formulario_id' => $formulario->id,
            'nome' => $record->nome,
            'titulo' => $record->nome,
            'nome_fisico' => $record->nome_fisico,
            'nome_arquivo' => $record->nomeClasse().'Resource',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
