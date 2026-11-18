<?php

namespace App\Models\Md;

use App\Models\Tenant;
use App\Sistema\Enums\Banco;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Modulo extends Model
{
    protected $connection = 'central';

    protected $table = 'modulos';

    protected $fillable = ['nome', 'prefixo', 'pasta', 'banco', 'descricao', 'ordem', 'padrao', 'roles', 'roles_gerados'];

    protected function casts(): array
    {
        return [
            'banco' => Banco::class,
            'roles' => 'array',
            'roles_gerados' => 'array',
            'ordem' => 'integer',
            'padrao' => 'boolean',
        ];
    }

    /**
     * O módulo está liberado para a empresa? Os de clientes só valem se forem padrão (todos têm) ou
     * estiverem cadastrados na empresa; os da base pai não dependem de empresa.
     */
    public function liberadoPara(?Tenant $empresa): bool
    {
        if ($this->banco === Banco::Central || $this->padrao) {
            return true;
        }

        return $empresa !== null && in_array((int) $this->getKey(), $empresa->idsDosModulos(), true);
    }

    protected static function booted(): void
    {
        // deixar de ser padrão: nenhum campo de outro módulo pode continuar apontando para as tabelas dele
        static::saving(function (Modulo $modulo) {
            if (! $modulo->exists || ! $modulo->isDirty('padrao') || $modulo->padrao) {
                return;
            }

            $fora = MdCampo::query()
                ->whereHas('fkTabela', fn ($q) => $q->where('modulo_id', $modulo->id))
                ->whereHas('tabela', fn ($q) => $q->where('modulo_id', '!=', $modulo->id))
                ->count();

            if ($fora > 0) {
                throw ValidationException::withMessages([
                    'padrao' => "{$fora} campo(s) de outros módulos apontam para tabelas de {$modulo->nome}. Desfaça essas referências antes de tirar a marca de módulo padrão.",
                ]);
            }
        });
    }

    public function tabelas(): HasMany
    {
        return $this->hasMany(MdTabela::class);
    }

    /**
     * Empresas que têm o módulo cadastrado (inversa de Tenant::modulos()).
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'empresa_modulos', 'modulo_id', 'tenant_id')->withTimestamps();
    }

    /**
     * Permissão de acesso ao módulo (criada no central e em cada cliente).
     */
    public function permissao(): string
    {
        return static::permissaoDo($this->prefixo);
    }

    public static function permissaoDo(string $prefixo): string
    {
        return "modulo_{$prefixo}";
    }

    public function namespace(): string
    {
        return "App\\Modulos\\{$this->pasta}";
    }

    public function caminho(string $arquivo = ''): string
    {
        return app_path(rtrim("Modulos/{$this->pasta}/{$arquivo}", '/'));
    }
}
