<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdCampo;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdFormularioPagina;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdStatus;
use App\Models\Md\MdTabela;
use App\Models\Md\MdTabelaTraducao;
use App\Models\Md\Modulo;
use App\Sistema\Registro;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Dicionário de dados em JSON (database/md), versionado no git. As telas são
 * montadas a partir do banco central; em outra máquina ou no cliente o
 * cadastro é remontado com: php artisan md:importar.
 */
class Exportador
{
    public static function pasta(string $arquivo = ''): string
    {
        return database_path(rtrim("md/{$arquivo}", '/'));
    }

    public static function caminhoTabela(MdTabela $tabela): string
    {
        return static::pasta("tabelas/{$tabela->banco->value}_{$tabela->nome_fisico}.json");
    }

    public static function modulos(): void
    {
        static::gravar(static::pasta('modulos.json'), Modulo::orderBy('prefixo')->get()
            ->map(fn (Modulo $m) => [...$m->only(['nome', 'prefixo', 'pasta', 'descricao', 'ordem', 'roles', 'roles_gerados']), 'banco' => $m->banco->value])
            ->all());
    }

    public static function status(): void
    {
        static::gravar(static::pasta('status.json'), MdStatus::orderBy('nome_fisico')->get()
            ->map(fn (MdStatus $s) => $s->only(['nome', 'nome_fisico', 'icone', 'cor', 'descricao']))
            ->all());
    }

    public static function tabela(MdTabela $tabela): void
    {
        $tabela->load(['modulo', 'campos.fkTabela', 'situacoes.status', 'traducoes', 'itens', 'formularios.botoes', 'formularios.componentes', 'gerenciamentos.botoes', 'gerenciamentos.formulario']);

        $sem = fn (array $dados, array $chaves) => collect($dados)->except(['id', 'created_at', 'updated_at', ...$chaves])->all();

        static::gravar(static::caminhoTabela($tabela), [
            ...$tabela->only(['nome', 'nome_fisico', 'nome_controller', 'descricao', 'icone', 'cor', 'controla_situacao', 'campo_exibicao', 'generated_schema', 'itens_gerados']),
            'modulo' => $tabela->modulo->prefixo,
            'banco' => $tabela->banco->value,
            'tipo' => $tabela->tipoTabela()->value,
            'generated_at' => $tabela->generated_at?->toIso8601String(),
            'campos' => $tabela->campos->map(fn (MdCampo $c) => [
                ...$sem($c->attributesToArray(), ['md_tabela_id', 'fk_tabela_id']),
                'tipo' => $c->tipo->value,
                // FK pelo nome (os ids mudam de uma máquina para outra)
                'fk' => $c->fkTabela ? ['banco' => $c->fkTabela->banco->value, 'tabela' => $c->fkTabela->nome_fisico] : null,
            ])->values()->all(),
            'situacoes' => $tabela->situacoes->map(fn ($s) => [
                'status' => $s->status->nome_fisico, 'padrao' => $s->padrao, 'ordem' => $s->ordem,
            ])->values()->all(),
            'traducoes' => $tabela->traducoes->map(fn ($t) => $sem($t->attributesToArray(), ['md_tabela_id']))->values()->all(),
            'itens' => $tabela->itens->map(fn ($i) => $sem($i->attributesToArray(), ['md_tabela_id']))->values()->all(),
            'formularios' => $tabela->formularios->map(fn (MdFormulario $f) => [
                ...$sem($f->attributesToArray(), ['md_tabela_id']),
                'botoes' => $f->botoes->map(fn ($b) => $sem($b->attributesToArray(), ['md_formulario_id']))->values()->all(),
                // tab pages: a tabela de destino vai pelo nome (os ids mudam de uma máquina para outra)
                'tab_pages' => $f->tabPages()->with('tabela')->get()->map(fn (MdFormularioPagina $p) => [
                    ...$sem($p->attributesToArray(), ['md_formulario_id', 'tabela_id']),
                    'tipo' => $p->tipo->value,
                    'tabela' => $p->tabela ? ['banco' => $p->tabela->banco->value, 'tabela' => $p->tabela->nome_fisico] : null,
                ])->values()->all(),
                // a árvore: as traduções vão pelo nome (tabela + tipo + nome); a tab page, pelo nome físico
                'componentes' => static::layoutComReferencias($f->layout()),
            ])->values()->all(),
            'gerenciamentos' => $tabela->gerenciamentos->map(fn (MdGerenciamento $g) => [
                ...$sem($g->attributesToArray(), ['md_tabela_id', 'md_formulario_id']),
                'colunas' => collect($g->colunas ?? [])->map(fn ($c) => [...$c, 'traducao' => static::referencia($c['traducao'] ?? null)])->all(),
                'formulario' => $g->formulario?->nome_fisico,
                'botoes' => $g->botoes->map(fn ($b) => $sem($b->attributesToArray(), ['md_gerenciamento_id']))->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * Tradução pelo id -> {tabela, tipo, nome} (estável entre máquinas).
     */
    protected static function referencia(mixed $traducaoId): ?array
    {
        $t = is_numeric($traducaoId) ? MdTabelaTraducao::with('tabela')->find((int) $traducaoId) : null;

        return $t ? ['tabela' => $t->tabela->nome_fisico, 'tipo' => $t->tipo, 'nome' => $t->nome] : null;
    }

    protected static function idDaReferencia(?array $referencia): ?int
    {
        if (! $referencia) {
            return null;
        }

        return MdTabelaTraducao::whereHas('tabela', fn ($q) => $q->where('nome_fisico', $referencia['tabela']))
            ->where('tipo', $referencia['tipo'])->where('nome', $referencia['nome'])->value('id');
    }

    protected static function layoutComReferencias(array $layout): array
    {
        $trocar = fn (array $e) => [...collect($e)->except('traducao_id')->all(), 'traducao' => static::referencia($e['traducao_id'] ?? null)];

        return array_map(fn ($p) => [...$p, 'pagina' => $p['pagina']['nome_fisico'] ?? null, 'campos' => array_map(fn ($e) => [
            ...$trocar($e),
            ...(isset($e['campos']) ? ['campos' => array_map($trocar, $e['campos'])] : []),
        ], $p['campos'] ?? [])], $layout);
    }

    protected static function layoutComIds(array $layout): array
    {
        $trocar = fn (array $e) => [...collect($e)->except('traducao')->all(), 'traducao_id' => static::idDaReferencia($e['traducao'] ?? null)];

        return array_map(fn ($p) => [...$p, 'campos' => array_map(fn ($e) => [
            ...$trocar($e),
            ...(isset($e['campos']) ? ['campos' => array_map($trocar, $e['campos'])] : []),
        ], $p['campos'] ?? [])], $layout);
    }

    public static function removerTabela(MdTabela $tabela): void
    {
        Arquivos::apagar(static::caminhoTabela($tabela));
    }

    protected static function gravar(string $caminho, array $dados): void
    {
        Arquivos::gravar($caminho, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }

    protected static function ler(string $caminho): array
    {
        return is_file($caminho) ? json_decode(file_get_contents($caminho), true, flags: JSON_THROW_ON_ERROR) : [];
    }

    /**
     * Recria/atualiza o cadastro a partir dos JSON.
     *
     * @return list<string> o que foi importado
     */
    public static function importar(): array
    {
        $tabelas = array_map(fn ($f) => static::ler($f), glob(static::pasta('tabelas/*.json')) ?: []);

        $resultado = DB::connection('central')->transaction(function () use ($tabelas) {
            $log = [];

            foreach (static::ler(static::pasta('modulos.json')) as $m) {
                Modulo::updateOrCreate(['prefixo' => $m['prefixo']], $m);
                $log[] = "Módulo {$m['nome']}";
            }

            foreach (static::ler(static::pasta('status.json')) as $s) {
                MdStatus::updateOrCreate(['nome_fisico' => $s['nome_fisico']], $s);
            }

            // 1) tabelas (as FKs precisam de todas criadas)
            $pares = [];
            foreach ($tabelas as $t) {
                $modulo = Modulo::where('prefixo', $t['modulo'])->first()
                    ?? throw new RuntimeException("Tabela {$t['nome_fisico']}: módulo {$t['modulo']} não encontrado.");

                $pares[] = [$t, MdTabela::updateOrCreate(
                    ['banco' => $t['banco'], 'nome_fisico' => $t['nome_fisico']],
                    [...collect($t)->except(['campos', 'situacoes', 'traducoes', 'itens', 'formularios', 'gerenciamentos', 'modulo', 'banco', 'nome_fisico'])->all(), 'modulo_id' => $modulo->id],
                )];
            }

            // 2) campos (pelo uid), situações, traduções e itens
            foreach ($pares as [$t, $tabela]) {
                $uids = [];

                foreach ($t['campos'] as $c) {
                    $fk = $c['fk'] ? MdTabela::where('banco', $c['fk']['banco'])->where('nome_fisico', $c['fk']['tabela'])->first() : null;

                    if ($c['fk'] && ! $fk) {
                        throw new RuntimeException("{$tabela->nome_fisico}.{$c['nome_fisico']}: tabela da FK {$c['fk']['tabela']} não encontrada.");
                    }

                    MdCampo::updateOrCreate(['uid' => $c['uid']], [
                        ...collect($c)->except(['fk', 'uid'])->all(),
                        'md_tabela_id' => $tabela->id,
                        'fk_tabela_id' => $fk?->id,
                    ]);

                    $uids[] = $c['uid'];
                }

                $tabela->campos()->whereNotIn('uid', $uids)->delete();

                $tabela->situacoes()->delete();
                foreach ($t['situacoes'] as $s) {
                    $tabela->situacoes()->create([
                        'md_status_id' => MdStatus::where('nome_fisico', $s['status'])->value('id')
                            ?? throw new RuntimeException("{$tabela->nome_fisico}: situação {$s['status']} não encontrada."),
                        'padrao' => $s['padrao'],
                        'ordem' => $s['ordem'],
                    ]);
                }

                // traduções pelo nome (os componentes e colunas apontam para elas)
                $nomes = [];
                foreach ($t['traducoes'] ?? [] as $tr) {
                    MdTabelaTraducao::updateOrCreate(['md_tabela_id' => $tabela->id, 'tipo' => $tr['tipo'], 'nome' => $tr['nome']], $tr);
                    $nomes[] = "{$tr['tipo']}:{$tr['nome']}";
                }
                $tabela->traducoes()->get()->reject(fn ($tr) => in_array("{$tr->tipo}:{$tr->nome}", $nomes, true))->each->delete();

                $tabela->itens()->delete();
                foreach ($t['itens'] ?? [] as $item) {
                    $tabela->itens()->create($item);
                }
            }

            // 3) formulários (componentes) e gerenciamentos: as traduções de todas as tabelas já existem
            foreach ($pares as [$t, $tabela]) {
                $formularios = [];
                foreach ($t['formularios'] as $f) {
                    $formulario = MdFormulario::updateOrCreate(['nome_fisico' => $f['nome_fisico']], [
                        ...collect($f)->except(['botoes', 'componentes', 'tab_pages', 'nome_fisico'])->all(), 'md_tabela_id' => $tabela->id,
                    ]);
                    $formulario->botoes()->delete();
                    foreach ($f['botoes'] as $b) {
                        $formulario->botoes()->create($b);
                    }

                    // tab pages antes dos componentes (eles apontam para elas pelo nome físico)
                    $tabPages = [];
                    foreach ($f['tab_pages'] ?? [] as $p) {
                        $destino = $p['tabela'] ?? null;
                        $tabPage = $formulario->tabPages()->updateOrCreate(['nome_fisico' => $p['nome_fisico']], [
                            ...collect($p)->except(['tabela', 'nome_fisico'])->all(),
                            'tabela_id' => $destino ? MdTabela::where('nome_fisico', $destino['tabela'])->where('banco', $destino['banco'])->value('id') : null,
                        ]);
                        $tabPages[] = $tabPage->id;
                    }
                    $formulario->tabPages()->whereNotIn('id', $tabPages)->delete();

                    $formulario->salvarLayout(static::layoutComIds($f['componentes'] ?? []));
                    $formularios[] = $formulario->id;
                }
                $tabela->formularios()->whereNotIn('id', $formularios)->delete();

                $gerenciamentos = [];
                foreach ($t['gerenciamentos'] as $g) {
                    $gerenciamento = MdGerenciamento::updateOrCreate(['nome_fisico' => $g['nome_fisico']], [
                        ...collect($g)->except(['botoes', 'formulario', 'nome_fisico', 'colunas'])->all(),
                        'colunas' => collect($g['colunas'] ?? [])->map(fn ($c) => [...collect($c)->except('traducao')->all(), 'traducao' => static::idDaReferencia($c['traducao'] ?? null)])->all() ?: null,
                        'md_tabela_id' => $tabela->id,
                        'md_formulario_id' => $g['formulario'] ? MdFormulario::where('nome_fisico', $g['formulario'])->value('id') : null,
                    ]);
                    $gerenciamento->botoes()->delete();
                    foreach ($g['botoes'] as $b) {
                        $gerenciamento->botoes()->create($b);
                    }
                    $gerenciamentos[] = $gerenciamento->id;
                }
                $tabela->gerenciamentos()->whereNotIn('id', $gerenciamentos)->delete();

                $log[] = "Tabela {$tabela->banco->getLabel()}: {$tabela->nome_fisico}";
            }

            return $log;
        });

        Registro::limpar();

        return $resultado;
    }
}
