<?php

namespace App\Sistema\Runtime;

use App\Filament\Sistema\Pages\ModuloPage;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Menu lateral dos módulos: cada módulo do banco do painel (base pai no
 * central, clientes nas empresas) vira um grupo, com a visão geral e os
 * gerenciamentos das tabelas dele. O grupo aparece desde a criação do módulo.
 */
class ModuloNavegacao
{
    /**
     * @return array{0: list<NavigationGroup>, 1: list<NavigationItem>}
     */
    public static function para(string $painel): array
    {
        $banco = Banco::doPainel($painel);

        if (! $banco || ! auth()->check() || ! static::dicionarioInstalado()) {
            return [[], []];
        }

        $grupos = [];
        $itens = [];

        $modulos = Modulo::query()
            ->where('banco', $banco->value)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->with('tabelas.gerenciamentos')
            ->get();

        foreach ($modulos as $modulo) {
            if (! Acesso::modulo($modulo)) {
                continue;
            }

            // grupo sem ícone: os itens têm o próprio ícone (com ícone no grupo, o Filament liga os itens como etapas)
            $grupos[] = NavigationGroup::make($modulo->nome);

            $itens[] = NavigationItem::make('Visão geral')
                ->key(static::chaveModulo($modulo))
                ->group($modulo->nome)
                ->icon('heroicon-o-squares-2x2')
                ->sort(-1)
                ->url(fn () => ModuloPage::getUrl(['modulo' => $modulo->prefixo], panel: $painel))
                ->isActiveWhen(fn () => static::rotaAtual(ModuloPage::class, 'modulo', $modulo->prefixo));

            // as telas (Resources) entram no grupo sozinhas, pelo getNavigationGroup()
        }

        return [$grupos, $itens];
    }

    /**
     * Ícone do item: o do gerenciamento ou o da tabela, na cor da tabela.
     */
    public static function icone(MdGerenciamento $gerenciamento): string|\Illuminate\Support\HtmlString
    {
        $tabela = $gerenciamento->tabela;

        return MdTabela::iconeColorido($gerenciamento->icone ?: ($tabela->icone ?: 'heroicon-o-table-cells'), $tabela->cor);
    }

    public static function chaveModulo(Modulo $modulo): string
    {
        return "modulo:{$modulo->prefixo}";
    }

    /**
     * Itens dos módulos do painel para a tela Navegação (todos, sem filtrar acesso).
     *
     * @return array<string, array{label: string, group: ?string, icon: ?string, sort: int}>
     */
    public static function catalogo(string $painel): array
    {
        $banco = Banco::doPainel($painel);

        if (! $banco || ! static::dicionarioInstalado()) {
            return [];
        }

        $itens = [];

        foreach (Modulo::where('banco', $banco->value)->orderBy('ordem')->orderBy('nome')->with('tabelas.gerenciamentos')->get() as $modulo) {
            // as telas (Resources) entram no catálogo como qualquer resource do painel
            $itens[static::chaveModulo($modulo)] = ['label' => 'Visão geral', 'group' => $modulo->nome, 'icon' => 'heroicon-o-squares-2x2', 'sort' => -1];
        }

        return $itens;
    }

    protected static function rotaAtual(string $pagina, string $parametro, string $valor): bool
    {
        $rota = request()->route();

        return $rota && $rota->getAction('controller') === $pagina && $rota->parameter($parametro) === $valor;
    }

    protected static function dicionarioInstalado(): bool
    {
        try {
            return Schema::connection('central')->hasTable('modulos');
        } catch (Throwable) {
            return false;
        }
    }
}
