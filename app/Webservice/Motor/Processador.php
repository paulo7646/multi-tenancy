<?php

namespace App\Webservice\Motor;

use App\Webservice\Models\Integracao;
use App\Webservice\Models\Lote;
use App\Webservice\Models\Objeto;
use App\Webservice\Models\Versao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Percorre a árvore de objetos da versão com o conteúdo do retorno e roda os
 * eventos de cada item.
 *
 * Lotes: tudo roda dentro de um lote (uma transação). Objeto marcado "gera novo
 * lote" abre um lote por item, que pode falhar sozinho: o banco desfaz só aquele
 * lote e o processamento segue. Se o lote de fora falhar, os de dentro são
 * desfeitos junto (ficam marcados como erro).
 */
class Processador
{
    protected int $numero = 0;

    /** @var list<array{numero: int, objeto_id: int|null, pai: int|null, status: string, mensagem: string|null}> */
    protected array $lotes = [];

    /** @var list<int> índices dos lotes abertos (o último é o atual) */
    protected array $abertos = [];

    /** @var Collection<int, Collection<int, Objeto>> */
    protected Collection $porPai;

    public function __construct(protected Integracao $integracao, protected Versao $versao) {}

    /**
     * Processa e grava os lotes. Retorna o status final da integração.
     */
    public function processar(array $conteudo): string
    {
        $objetos = $this->versao->objetos()
            ->with(['eventos' => fn ($q) => $q->where('ativo', true)->orderBy('ordem')->orderBy('id')->with(['busca', 'atributos'])])
            ->get();

        $this->porPai = $objetos->groupBy(fn (Objeto $o) => $o->pai_id ?? 0);
        $raizes = $this->porPai->get(0, collect());

        if ($raizes->isEmpty()) {
            throw new ProcessamentoException('A versão não tem objetos cadastrados.');
        }

        try {
            $this->emLote(null, function () use ($raizes, $conteudo) {
                foreach ($raizes as $raiz) {
                    // a raiz (root) é o {} principal; uma raiz com outro nome busca esse nome dentro dele
                    $dados = $raiz->nome === 'root' ? $conteudo : ($conteudo[$raiz->nome] ?? null);

                    if ($dados === null) {
                        if ($raiz->obrigatorio) {
                            throw new ProcessamentoException("O objeto \"{$raiz->nome}\" é obrigatório e não veio no retorno.");
                        }

                        continue;
                    }

                    $this->no($raiz, $dados, []);
                }
            });
        } finally {
            $status = $this->gravarLotes();
        }

        return $status;
    }

    protected function no(Objeto $no, mixed $dados, array $ancestrais): void
    {
        $itens = $no->lista ? $this->itens($dados) : [$dados];

        foreach ($itens as $item) {
            $corpo = fn () => $this->conteudo($no, $item, $ancestrais);

            $no->novo_lote ? $this->emLote($no, $corpo) : $corpo();
        }
    }

    protected function conteudo(Objeto $no, mixed $item, array $ancestrais): void
    {
        $item = is_array($item) ? $item : ['valor' => $item];

        // atributos (valores simples) do item; os dos pais ficam disponíveis, o do item vence
        $dados = [...$ancestrais, ...array_filter($item, fn ($v) => ! is_array($v))];
        $lote = $this->lotes[array_key_last($this->abertos)]['numero'];

        foreach ($no->eventos as $evento) {
            ExecutorDeEventos::executar($evento, new ContextoEvento($this->integracao, $no, $evento, $item, $dados, $lote));
        }

        foreach ($this->porPai->get($no->id, collect()) as $filho) {
            $valor = $item[$filho->nome] ?? null;

            if ($valor === null) {
                if ($filho->obrigatorio) {
                    throw new ProcessamentoException("O objeto \"{$filho->nome}\" é obrigatório e não veio no retorno (dentro de \"{$no->nome}\").");
                }

                continue;
            }

            $this->no($filho, $valor, $dados);
        }
    }

    /**
     * @return list<mixed>
     */
    protected function itens(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [$valor];
        }

        // lista de itens, ou um objeto só (XML com um único elemento)
        return array_is_list($valor) ? $valor : [$valor];
    }

    protected function emLote(?Objeto $objeto, callable $corpo): void
    {
        $indice = count($this->lotes);
        $this->lotes[] = [
            'numero' => ++$this->numero,
            'objeto_id' => $objeto?->getKey(),
            'pai' => $this->abertos === [] ? null : $this->abertos[array_key_last($this->abertos)],
            'status' => 'ok',
            'mensagem' => null,
        ];
        $this->abertos[] = $indice;

        try {
            DB::transaction($corpo);
        } catch (Throwable $e) {
            $this->lotes[$indice]['status'] = 'erro';
            $this->lotes[$indice]['mensagem'] = $e->getMessage();
            $this->desfazerInternos($indice);
        } finally {
            array_pop($this->abertos);
        }
    }

    /**
     * Os lotes de dentro de um lote que falhou foram desfeitos junto com ele.
     */
    protected function desfazerInternos(int $indice): void
    {
        foreach ($this->lotes as $i => $lote) {
            if ($lote['pai'] === $indice && $lote['status'] === 'ok') {
                $this->lotes[$i]['status'] = 'erro';
                $this->lotes[$i]['mensagem'] = "Desfeito junto com o lote {$this->lotes[$indice]['numero']}.";
                $this->desfazerInternos($i);
            }
        }
    }

    /**
     * Grava os lotes depois de todas as transações (um rollback não leva o registro junto).
     * O lote de fora só aparece se falhou ou se não há lotes por item.
     */
    protected function gravarLotes(): string
    {
        $internos = collect($this->lotes)->contains(fn (array $l) => $l['pai'] !== null);

        $ocultar = fn (array $l) => $l['pai'] === null && $l['status'] === 'ok' && $internos;
        $gravar = collect($this->lotes)->reject($ocultar)->values();
        // sem o lote de fora, a numeração começa em 1
        $deslocamento = collect($this->lotes)->contains($ocultar) ? 1 : 0;

        foreach ($gravar as $lote) {
            Lote::create([
                'integracao_id' => $this->integracao->getKey(),
                'objeto_id' => $lote['objeto_id'],
                'numero' => $lote['numero'] - $deslocamento,
                'status' => $lote['status'],
                'mensagem' => $lote['mensagem'],
            ]);
        }

        $erros = $gravar->where('status', 'erro')->count();

        $this->integracao->forceFill([
            'lotes_total' => $gravar->count(),
            'lotes_erro' => $erros,
        ])->save();

        return match (true) {
            $erros === 0 => 'concluida',
            $erros === $gravar->count() => 'erro',
            default => 'parcial',
        };
    }
}
