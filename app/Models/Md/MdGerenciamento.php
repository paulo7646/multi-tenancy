<?php

namespace App\Models\Md;

use App\Sistema\Base\BaseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Gerenciamento: uma tela (Resource do Filament) da tabela, item do menu dentro do módulo.
 * Colunas: [{campo: uid|situacao, pesquisavel, ordenavel, oculta}].
 */
class MdGerenciamento extends Model
{
    protected $connection = 'central';

    protected $table = 'md_gerenciamentos';

    protected $fillable = [
        'md_tabela_id', 'md_formulario_id', 'nome', 'titulo', 'nome_fisico', 'nome_arquivo', 'icone', 'ordem', 'colunas', 'filtros',
    ];

    protected function casts(): array
    {
        return [
            'colunas' => 'array',
            'filtros' => 'array',
            'ordem' => 'integer',
        ];
    }

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'md_tabela_id');
    }

    public function formulario(): BelongsTo
    {
        return $this->belongsTo(MdFormulario::class, 'md_formulario_id');
    }

    public function botoes(): HasMany
    {
        return $this->hasMany(MdGerenciamentoBotao::class)->orderBy('ordem')->orderBy('id');
    }

    // ------------------------------------------------------------------
    // Resource do Filament (um por gerenciamento)
    // ------------------------------------------------------------------

    public function classeResource(): string
    {
        return $this->nome_arquivo;
    }

    public function resourceFqcn(): string
    {
        return $this->tabela->namespace() . '\\' . $this->classeResource();
    }

    /**
     * Permissão do Shield de uma ação do Filament (viewAny, create...):
     * view_any_op_produto, create_op_produto.
     */
    public function permissao(string $acao): string
    {
        return static::permissaoDo($this->nome_fisico, $acao);
    }

    public static function permissaoDo(string $nomeFisico, string $acao): string
    {
        return Str::snake($acao) . '_' . Str::lower($nomeFisico);
    }

    /**
     * @return list<string>
     */
    public function permissoes(): array
    {
        return array_map(fn (string $acao) => $this->permissao($acao), BaseResource::ACOES);
    }
}
