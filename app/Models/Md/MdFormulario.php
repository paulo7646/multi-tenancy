<?php

namespace App\Models\Md;

use App\Sistema\Enums\TipoComponente;
use App\Sistema\Runtime\Layout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Formulário de uma tabela. Os componentes ficam em md_formulario_componentes;
 * as páginas (títulos) em "paginas". O designer e o builder trabalham com a
 * árvore [{titulo, campos: [elemento]}] (veja App\Sistema\Runtime\Layout).
 * Componente "situacao" é a situação da tabela (quando ela controla situação).
 */
class MdFormulario extends Model
{
    public const CAMPO_SITUACAO = 'situacao';

    protected $connection = 'central';

    protected $table = 'md_formularios';

    protected $fillable = ['md_tabela_id', 'nome', 'titulo', 'nome_fisico', 'nome_arquivo', 'paginado', 'colunas', 'paginas'];

    protected function casts(): array
    {
        return [
            'paginado' => 'boolean',
            'colunas' => 'integer',
            'paginas' => 'array',
        ];
    }

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(MdTabela::class, 'md_tabela_id');
    }

    public function botoes(): HasMany
    {
        return $this->hasMany(MdFormularioBotao::class)->orderBy('ordem')->orderBy('id');
    }

    /**
     * Todos os componentes (inclusive os de dentro dos grupos).
     */
    public function componentes(): HasMany
    {
        return $this->hasMany(MdFormularioComponente::class)->orderBy('pagina')->orderBy('ordem')->orderBy('id');
    }

    /**
     * Tab pages (os componentes sem tab page ficam na tab "Geral").
     */
    public function tabPages(): HasMany
    {
        return $this->hasMany(MdFormularioPagina::class)->orderBy('ordem')->orderBy('id');
    }

    /** Título da tab dos componentes sem tab page */
    public const TAB_GERAL = 'Geral';

    /**
     * Definição de uma tab page para o layout/builder (a mesma forma vem do estado da tela, na prévia).
     *
     * @return array{id: ?int, nome: string, nome_fisico: string, tipo: string, fk_campo: ?string, tabela_id: ?int, campo_tabela: ?string, campo_ligacao: ?string}
     */
    public static function definicaoTabPage(MdFormularioPagina|array $tabPage): array
    {
        $dados = $tabPage instanceof MdFormularioPagina ? [...$tabPage->attributesToArray(), 'tipo' => $tabPage->tipo->value] : $tabPage;

        return [
            'id' => isset($dados['id']) ? (int) $dados['id'] : null,
            'nome' => (string) ($dados['nome'] ?? ''),
            'nome_fisico' => (string) ($dados['nome_fisico'] ?? ''),
            'tipo' => (string) ($dados['tipo'] ?? 'campos'),
            'fk_campo' => $dados['fk_campo'] ?? null,
            'tabela_id' => filled($dados['tabela_id'] ?? null) ? (int) $dados['tabela_id'] : null,
            'campo_tabela' => $dados['campo_tabela'] ?? null,
            'campo_ligacao' => $dados['campo_ligacao'] ?? null,
        ];
    }

    // ------------------------------------------------------------------
    // Layout (árvore) <-> componentes (linhas)
    // ------------------------------------------------------------------

    /**
     * @return list<array{titulo: string, campos: list<array<string, mixed>>}>
     */
    public function layout(): array
    {
        $componentes = $this->componentes()->get();

        // tab "Geral" (componentes sem tab page) e depois as tab pages, na ordem
        $paginas = [['titulo' => static::TAB_GERAL, 'pagina' => null, 'campos' => static::arvore($componentes->whereNull('md_formulario_pagina_id'))]];

        foreach ($this->tabPages()->get() as $tabPage) {
            $paginas[] = [
                'titulo' => $tabPage->nome,
                'pagina' => static::definicaoTabPage($tabPage),
                'campos' => static::arvore($componentes->where('md_formulario_pagina_id', $tabPage->id)),
            ];
        }

        return $paginas;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, MdFormularioComponente>  $componentes
     * @return list<array<string, mixed>>
     */
    protected static function arvore(\Illuminate\Support\Collection $componentes): array
    {
        return $componentes->whereNull('pai_id')->sortBy('ordem')
            ->map(function (MdFormularioComponente $c) use ($componentes) {
                $elemento = static::elemento($c);

                if ($c->tipo === Layout::GRUPO) {
                    $elemento['campos'] = $componentes->where('pai_id', $c->id)->sortBy('ordem')
                        ->map(fn (MdFormularioComponente $f) => static::elemento($f))->values()->all();
                }

                return $elemento;
            })
            ->values()
            ->all();
    }

    protected static function elemento(MdFormularioComponente $c): array
    {
        return array_filter([
            'tipo' => $c->tipo === Layout::GRUPO ? Layout::GRUPO : Layout::CAMPO,
            'campo' => $c->campo,
            'componente' => $c->componente,
            'mascara' => $c->mascara,
            'traducao_id' => $c->traducao_id,
            'titulo' => $c->titulo,
            'colunas' => $c->colunas,
            'colunasGrupo' => $c->colunas_grupo,
            'quebra' => $c->quebra,
            'completar' => $c->completar,
            'obrigatorio' => $c->obrigatorio,
            'largura' => $c->largura,
            'altura' => $c->altura,
        ], fn ($v, $k) => $v !== null && ($v !== false || $k === 'obrigatorio'), ARRAY_FILTER_USE_BOTH); // obrigatorio false = "Não" (null segue o campo)
    }

    /**
     * Regrava os componentes a partir da árvore (a ordem é a da tela).
     */
    public function salvarLayout(?array $layout): void
    {
        $layout = array_values($layout ?: []);

        DB::connection($this->getConnectionName())->transaction(function () use ($layout) {
            $this->componentes()->delete();

            // tab page da página pelo nome físico ("pagina" pode vir como definição ou só o nome físico)
            $tabPages = $this->tabPages()->get()->keyBy('nome_fisico');

            foreach ($layout as $p => $pagina) {
                $nomeFisico = is_array($pagina['pagina'] ?? null) ? ($pagina['pagina']['nome_fisico'] ?? null) : ($pagina['pagina'] ?? null);
                $tabPageId = $nomeFisico ? $tabPages->get($nomeFisico)?->id : null;

                foreach (Layout::elementos($pagina['campos'] ?? []) as $ordem => $elemento) {
                    $pai = $this->criarComponente($elemento, $p, $ordem, null, $tabPageId);

                    if ($elemento['tipo'] === Layout::GRUPO) {
                        foreach (Layout::elementos($elemento['campos'] ?? []) as $o => $filho) {
                            if ($filho['tipo'] !== Layout::GRUPO) {
                                $this->criarComponente($filho, $p, $o, $pai->id, $tabPageId);
                            }
                        }
                    }
                }
            }

            $this->forceFill(['paginas' => array_map(fn ($p) => (string) ($p['titulo'] ?? static::TAB_GERAL), $layout) ?: [static::TAB_GERAL]])->saveQuietly();
        });
    }

    protected function criarComponente(array $e, int $pagina, int $ordem, ?int $pai, ?int $tabPageId = null): MdFormularioComponente
    {
        $grupo = $e['tipo'] === Layout::GRUPO;

        return $this->componentes()->create([
            'md_formulario_pagina_id' => $tabPageId,
            'pai_id' => $pai,
            'pagina' => $pagina,
            'ordem' => $ordem,
            'tipo' => $grupo ? Layout::GRUPO : Layout::CAMPO,
            'campo' => $grupo ? null : ($e['campo'] ?? null),
            // no grupo, o componente é o tipo de grupo (seção, moldura, grade)
            'componente' => $e['componente'] ?? null,
            'mascara' => $grupo ? null : (filled($e['mascara'] ?? null) ? (string) $e['mascara'] : null),
            'traducao_id' => $grupo ? null : (is_numeric($e['traducao_id'] ?? null) ? (int) $e['traducao_id'] : null),
            'titulo' => $e['titulo'] ?? null,
            'colunas' => max(1, min(4, (int) ($e['colunas'] ?? 1))),
            'colunas_grupo' => $grupo ? max(1, min(4, (int) ($e['colunasGrupo'] ?? 2))) : null,
            'quebra' => (bool) ($e['quebra'] ?? false),
            'completar' => (bool) ($e['completar'] ?? false),
            'obrigatorio' => isset($e['obrigatorio']) && $e['obrigatorio'] !== '' ? (bool) $e['obrigatorio'] : null,
            'largura' => Layout::pixels($e['largura'] ?? null),
            'altura' => Layout::pixels($e['altura'] ?? null),
        ]);
    }

    /**
     * Layout padrão: todos os campos com componente, numa página só.
     *
     * @return list<array{titulo: string, campos: list<array<string, mixed>>}>
     */
    public static function layoutPadrao(MdTabela $tabela): array
    {
        // já com o componente padrão de cada tipo de campo
        $campos = $tabela->campos()->where('sem_componente', false)->get()
            ->map(fn (MdCampo $c) => ['tipo' => Layout::CAMPO, 'campo' => $c->uid, 'componente' => TipoComponente::padrao($c->tipo)->value, 'colunas' => 1])
            ->all();

        if ($tabela->controla_situacao) {
            $campos[] = ['tipo' => Layout::CAMPO, 'campo' => static::CAMPO_SITUACAO, 'componente' => TipoComponente::paraSituacao()[0]->value, 'colunas' => 1];
        }

        return [['titulo' => static::TAB_GERAL, 'pagina' => null, 'campos' => $campos]];
    }
}
