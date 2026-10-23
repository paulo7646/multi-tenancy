<?php

namespace App\Filament\Resources\Dicionario\MdFormularioResource\Pages\Concerns;

use App\Filament\Resources\Dicionario\MdFormularioResource;
use App\Models\Md\MdFormulario;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\TipoTabPage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

/**
 * Designer da prévia do formulário: clique direito num campo (alinhar pela
 * sugestão, nova linha e "Ajustar campo": tab page, ordem, largura e altura).
 *
 * Cada campo da prévia sabe em que tab está: null = "Geral" (aba Componentes),
 * ou a chave da tab page no repeater da aba Tab pages (vale também para tab
 * pages ainda não salvas).
 *
 * Os ajustes ficam em $previa até o "Salvar" da prévia, que os grava nos
 * componentes (da "Geral" e das tab pages) e, ao editar, já salva o
 * formulário. Ao criar, grava junto com o "Criar".
 */
trait DesenhaPrevia
{
    /**
     * Ajustes da prévia, na ordem da tela.
     *
     * @var list<array{campo: string, aba: ?string, quebra: bool, completar: bool, largura: ?int, altura: ?int}>
     */
    public array $previa = [];

    /** Opções do clique direito que ligam/desligam */
    protected const OPCOES_PREVIA = ['quebra', 'completar'];

    /**
     * Carrega os ajustes a partir dos componentes (ou do layout padrão, se ainda não há componentes).
     */
    public function iniciarPrevia(): void
    {
        $this->previa = collect($this->itensDaPrevia())
            ->map(fn (array $par) => [
                'campo' => (string) $par['item']['campo'],
                'aba' => $par['aba'],
                'quebra' => (bool) ($par['item']['quebra'] ?? false),
                'completar' => (bool) ($par['item']['completar'] ?? false),
                'largura' => filled($par['item']['largura'] ?? null) ? (int) $par['item']['largura'] : null,
                'altura' => filled($par['item']['altura'] ?? null) ? (int) $par['item']['altura'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Clique direito: liga/desliga "alinhar pela sugestão" (completar) ou "nova linha" (quebra).
     */
    public function previaAlternar(string $campo, string $opcao): void
    {
        if (! in_array($opcao, self::OPCOES_PREVIA, true)) {
            return;
        }

        foreach ($this->previa as $i => $item) {
            if ($item['campo'] === $campo) {
                $this->previa[$i][$opcao] = ! $item[$opcao];
            }
        }

        $this->redesenharPrevia();
    }

    /**
     * O Filament monta o conteúdo do modal no começo da requisição (antes do
     * ajuste) e reaproveita na resposta: sem descartar, a tela mostraria o
     * estado anterior (parecendo invertido).
     */
    protected function redesenharPrevia(): void
    {
        $this->forgetCachedMountedActionSchemas();
    }

    // ------------------------------------------------------------------
    // "Ajustar campo": tab page, ordem, largura e altura
    // ------------------------------------------------------------------

    /**
     * @return array{aba: string, posicao: int, largura: ?int, altura: ?int}
     */
    public function previaAjusteAtual(string $campo): array
    {
        $item = collect($this->previa)->firstWhere('campo', $campo) ?? [];

        return [
            'aba' => $this->chaveAba($item['aba'] ?? null),
            'posicao' => $this->posicaoNaAba($campo),
            'largura' => $item['largura'] ?? null,
            'altura' => $item['altura'] ?? null,
        ];
    }

    /**
     * Grava o "Ajustar campo". Mudando de tab, o campo vai para a posição
     * escolhida na tab nova; as quebras de linha ficam nas posições (as linhas
     * de cada tab continuam as mesmas).
     */
    public function previaAjustar(string $campo, ?string $aba, int $posicao, mixed $largura, mixed $altura): void
    {
        $item = collect($this->previa)->firstWhere('campo', $campo);

        if (! $item) {
            return;
        }

        $aba = $this->abaDaChave($aba);

        if (! array_key_exists($this->chaveAba($aba), $this->abasPermitidas($campo))) {
            $aba = $item['aba'];
        }

        $item = [
            ...$item,
            'largura' => filled($largura) ? (int) $largura : null,
            'altura' => filled($altura) ? (int) $altura : null,
        ];

        $mudouDeTab = $item['aba'] !== $aba;
        $quebrasOrigem = $this->quebrasDaAba($item['aba']);
        $quebrasDestino = $this->quebrasDaAba($aba);

        // tira da tab de origem (as quebras continuam nas posições)
        $resto = collect($this->previa)->reject(fn (array $i) => $i['campo'] === $campo)->values();
        $resto = $this->reaplicarQuebras($resto, $item['aba'], $mudouDeTab ? $quebrasOrigem : null);

        // coloca na posição da tab de destino
        $naAba = $resto->filter(fn (array $i) => $i['aba'] === $aba)->values()->all();
        array_splice($naAba, max(0, min($posicao - 1, count($naAba))), 0, [[...$item, 'aba' => $aba]]);

        $outros = $resto->reject(fn (array $i) => $i['aba'] === $aba)->values();
        $this->previa = $this->reaplicarQuebras($outros->merge($naAba)->values(), $aba, $quebrasDestino)->all();

        $this->redesenharPrevia();
    }

    /**
     * Tabs para onde o campo pode ir: campos da tabela entre a "Geral" e as tab
     * pages "Campos da tabela"; campos da tabela pai entre as tab pages "Campos
     * da tabela pai" com a mesma ligação (FK).
     *
     * @return array<string, string> chave => título
     */
    public function abasPermitidas(string $campo): array
    {
        $atual = collect($this->previa)->firstWhere('campo', $campo)['aba'] ?? null;
        $tabPages = $this->tabPagesDaTela();
        $origem = $atual !== null ? ($tabPages[$atual] ?? null) : null;

        if ($origem && $origem['tipo'] === TipoTabPage::Pai->value) {
            return collect($tabPages)
                ->filter(fn (array $p) => $p['tipo'] === TipoTabPage::Pai->value && $p['fk_campo'] === $origem['fk_campo'])
                ->mapWithKeys(fn (array $p, string $chave) => [$chave => $p['nome'] ?: 'Tab page sem nome'])
                ->all();
        }

        return [
            '' => MdFormulario::TAB_GERAL,
            ...collect($tabPages)
                ->filter(fn (array $p) => $p['tipo'] === TipoTabPage::Campos->value)
                ->mapWithKeys(fn (array $p, string $chave) => [$chave => $p['nome'] ?: 'Tab page sem nome'])
                ->all(),
        ];
    }

    /**
     * Posições da tab escolhida: posição => quem está nela hoje (+ "no fim" se o campo vem de outra tab).
     *
     * @param  array<string, string>  $nomes  uid => nome do campo
     * @return array<int, string>
     */
    public function posicoesNaAba(string $campo, ?string $aba, array $nomes): array
    {
        $aba = $this->abaDaChave($aba);
        $itens = collect($this->previa)->filter(fn (array $i) => $i['aba'] === $aba)->values();
        $mesmaTab = $itens->contains(fn (array $i) => $i['campo'] === $campo);

        $posicoes = $itens->mapWithKeys(fn (array $i, int $indice) => [$indice + 1 => ($indice + 1) . 'º — ' . ($nomes[$i['campo']] ?? 'campo')])->all();

        if (! $mesmaTab) {
            $posicoes[count($posicoes) + 1] = (count($posicoes) + 1) . 'º — no fim';
        }

        return $posicoes;
    }

    protected function posicaoNaAba(string $campo): int
    {
        $aba = collect($this->previa)->firstWhere('campo', $campo)['aba'] ?? null;

        return (int) collect($this->previa)->filter(fn (array $i) => $i['aba'] === $aba)->values()->search(fn (array $i) => $i['campo'] === $campo) + 1;
    }

    /** @return list<bool> */
    protected function quebrasDaAba(?string $aba): array
    {
        return collect($this->previa)->filter(fn (array $i) => $i['aba'] === $aba)->pluck('quebra')->values()->all();
    }

    /**
     * Devolve às posições da tab as quebras de antes (null = mantém as de cada campo).
     *
     * @param  \Illuminate\Support\Collection<int, array>  $itens
     * @param  list<bool>|null  $quebras
     * @return \Illuminate\Support\Collection<int, array>
     */
    protected function reaplicarQuebras(\Illuminate\Support\Collection $itens, ?string $aba, ?array $quebras): \Illuminate\Support\Collection
    {
        if ($quebras === null) {
            return $itens;
        }

        $indice = 0;

        return $itens->map(function (array $i) use ($aba, $quebras, &$indice) {
            return $i['aba'] === $aba ? [...$i, 'quebra' => (bool) ($quebras[$indice++] ?? false)] : $i;
        });
    }

    /** Selects não gostam de chave null: a "Geral" vira '' */
    protected function chaveAba(?string $aba): string
    {
        return $aba ?? '';
    }

    protected function abaDaChave(?string $chave): ?string
    {
        return filled($chave) ? $chave : null;
    }

    // ------------------------------------------------------------------
    // Salvar
    // ------------------------------------------------------------------

    /**
     * "Salvar" da prévia: grava tab, ordem, nova linha, alinhar e tamanho nos
     * componentes da "Geral" (aba Componentes) e das tab pages.
     */
    public function aplicarPrevia(): void
    {
        $porCampo = collect($this->itensDaPrevia())->keyBy(fn (array $par) => (string) $par['item']['campo']);
        $listas = ['' => []];

        foreach (array_keys($this->tabPagesDaTela()) as $chave) {
            $listas[$chave] = [];
        }

        foreach ($this->previa as $ajuste) {
            $par = $porCampo->get($ajuste['campo']);
            $destino = $this->chaveAba($ajuste['aba']);

            if (! $par || ! array_key_exists($destino, $listas)) {
                continue;
            }

            // mudou de tab: entra como item novo no repeater de destino
            $chave = $par['aba'] === $ajuste['aba'] ? $par['chave'] : (string) Str::uuid();

            $listas[$destino][$chave] = [
                ...$par['item'],
                'quebra' => $ajuste['quebra'],
                'completar' => $ajuste['completar'],
                'largura' => $ajuste['largura'],
                'altura' => $ajuste['altura'],
            ];
        }

        // campos que não apareceram na prévia (ocultos) continuam onde estavam, no fim
        foreach ($porCampo as $par) {
            $origem = $this->chaveAba($par['aba']);

            if (! collect($listas)->contains(fn (array $lista) => collect($lista)->contains(fn ($item) => $item['campo'] === $par['item']['campo']))) {
                $listas[$origem][$par['chave']] = $par['item'];
            }
        }

        $this->data['componentes'] = $listas[''];

        foreach (array_keys($this->tabPagesDaTela()) as $chave) {
            if (TipoTabPage::tryFrom((string) $this->valorEnum($this->data['tabPages'][$chave]['tipo'] ?? null))?->temComponentes()) {
                $this->data['tabPages'][$chave]['componentes'] = $listas[$chave];
            }
        }

        // editando: grava o formulário na hora (validações e publicação de sempre)
        if ($this instanceof EditRecord) {
            $this->save();

            return;
        }

        Notification::make()
            ->title('Layout aplicado aos componentes')
            ->body('O formulário ainda não existe: clique em Criar para gravar.')
            ->success()
            ->send();
    }

    // ------------------------------------------------------------------
    // Estado da tela
    // ------------------------------------------------------------------

    /**
     * Tab pages da aba "Tab pages" (inclusive as não salvas), pela chave do repeater, na ordem.
     *
     * @return array<string, array{id: ?int, nome: string, nome_fisico: string, tipo: string, fk_campo: ?string, tabela_id: ?int, campo_tabela: ?string, campo_ligacao: ?string}>
     */
    public function tabPagesDaTela(): array
    {
        return collect($this->data['tabPages'] ?? [])
            ->filter(fn ($p) => is_array($p))
            ->map(fn (array $p, string $chave) => MdFormulario::definicaoTabPage([
                ...$p,
                // tab page salva: o id vem da chave do repeater (record-{id})
                'id' => str_starts_with($chave, 'record-') ? (int) substr($chave, 7) : null,
                'tipo' => $this->valorEnum($p['tipo'] ?? null) ?: TipoTabPage::Campos->value,
            ]))
            ->all();
    }

    /**
     * Componentes da "Geral" e das tab pages (com componentes), com a tab e a
     * chave do repeater. Sem nenhum, os do layout padrão da tabela (na "Geral").
     *
     * @return list<array{aba: ?string, chave: string, item: array<string, mixed>}>
     */
    protected function itensDaPrevia(): array
    {
        $validos = fn ($itens) => collect($itens ?? [])->filter(fn ($item) => is_array($item) && filled($item['campo'] ?? null));

        $itens = $validos($this->data['componentes'] ?? [])
            ->map(fn (array $item, string $chave) => ['aba' => null, 'chave' => $chave, 'item' => $item])
            ->values();

        foreach ($this->tabPagesDaTela() as $chaveTab => $tabPage) {
            if (! TipoTabPage::from($tabPage['tipo'])->temComponentes()) {
                continue;
            }

            $itens = $itens->merge($validos($this->data['tabPages'][$chaveTab]['componentes'] ?? [])
                ->map(fn (array $item, string $chave) => ['aba' => $chaveTab, 'chave' => $chave, 'item' => $item])
                ->values());
        }

        if ($itens->isEmpty() && ($tabela = MdTabela::with('modulo')->find($this->data['md_tabela_id'] ?? null))) {
            $itens = collect(MdFormularioResource::componentesPadrao($tabela))
                ->map(fn (array $item, string $chave) => ['aba' => null, 'chave' => $chave, 'item' => $item])
                ->values();
        }

        return $itens->all();
    }

    protected function valorEnum(mixed $valor): mixed
    {
        return $valor instanceof \BackedEnum ? $valor->value : $valor;
    }
}
