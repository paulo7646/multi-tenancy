<?php

use App\Sistema\Permissoes;
use Illuminate\Database\Migrations\Migration;

/**
 * Permissões do Shield das telas de webservice (webservice, operação, versão,
 * objeto, evento, integração e agendamento), no banco central e nos clientes.
 * Concedidas ao super_admin; os demais papéis são ajustados no Shield.
 */
return new class extends Migration
{
    private const RECURSOS = ['webservice', 'operacao', 'versao', 'objeto', 'evento', 'integracao', 'agendamento'];

    private const ACOES = [
        'view_any', 'view', 'create', 'update', 'delete', 'delete_any',
        'restore', 'restore_any', 'force_delete', 'force_delete_any', 'replicate', 'reorder',
    ];

    public function up(): void
    {
        Permissoes::criar($this->permissoes(), []);
    }

    public function down(): void
    {
        Permissoes::remover(...$this->permissoes());
    }

    /**
     * @return list<string>
     */
    private function permissoes(): array
    {
        return collect(self::RECURSOS)
            ->flatMap(fn (string $recurso) => array_map(fn (string $acao) => "{$acao}_{$recurso}", self::ACOES))
            ->all();
    }
};
