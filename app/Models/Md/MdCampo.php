<?php

namespace App\Models\Md;

use App\Sistema\Enums\TipoCampo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MdCampo extends Model
{
    protected $connection = 'central';

    protected $table = 'md_campos';

    protected $fillable = [
        'uid', 'md_tabela_id', 'nome', 'nome_fisico', 'tipo', 'tamanho', 'precisao', 'escala', 'opcoes',
        'obrigatorio', 'unico', 'indice', 'sem_componente', 'sistema', 'padrao', 'ajuda', 'fk_tabela_id', 'fk_on_delete', 'ordem',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCampo::class,
            'opcoes' => 'array',
            'obrigatorio' => 'boolean',
            'unico' => 'boolean',
            'indice' => 'boolean',
            'sem_componente' => 'boolean',
            'sistema' => 'boolean',
            'tamanho' => 'integer',
            'precisao' => 'integer',
            'escala' => 'integer',
            'ordem' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MdCampo $campo) {
            $campo->uid ??= (string) Str::uuid();
        });

        // uma tabela só referencia o próprio módulo ou um módulo padrão (Estrutura): o cliente pode não ter o outro
        static::saving(function (MdCampo $campo) {
            if (! $campo->isFk() || blank($campo->fk_tabela_id) || ! $campo->isDirty(['fk_tabela_id', 'md_tabela_id', 'tipo'])) {
                return;
            }

            $origem = $campo->tabela;
            $alvo = $campo->fkTabela;

            if ($origem && $alvo && ! $origem->podeReferenciar($alvo)) {
                throw ValidationException::withMessages([
                    'fk_tabela_id' => "A tabela {$alvo->nome_fisico} é de outro módulo. Só dá para referenciar tabelas do próprio módulo ou do módulo padrão (Estrutura).",
                ]);
            }
        });
    }

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'md_tabela_id');
    }

    public function fkTabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'fk_tabela_id');
    }

    public function isFk(): bool
    {
        return $this->tipo === TipoCampo::ForeignId;
    }

    /**
     * Nome do relacionamento no model: CLIENTE_ID -> cliente.
     */
    public function nomeRelacao(): string
    {
        $nome = mb_strtolower($this->nome_fisico);

        return Str::camel(str_ends_with($nome, '_id') ? substr($nome, 0, -3) : $nome);
    }
}
