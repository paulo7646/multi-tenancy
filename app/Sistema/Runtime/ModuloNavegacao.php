<?php

namespace App\Sistema\Runtime;

use App\Models\Md\MdGerenciamento;
use App\Models\Md\MdTabela;
use App\Models\Md\Modulo;
use App\Sistema\Enums\Banco;
use Filament\Navigation\NavigationGroup;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Menu lateral dos módulos: cada módulo do banco do painel (base pai no
 * central, clientes nas empresas) vira um grupo, com os
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

            // grupo sem ícone: as telas têm o próprio ícone (com ícone no grupo, o Filament troca os das telas por bolinhas)
            $grupos[] = NavigationGroup::make($modulo->nome);

            // as telas (Resources) entram no grupo sozinhas, pelo getNavigationGroup()
        }

        return [$grupos, $itens];
    }

    /**
     * Ícone do item: o do gerenciamento ou o da tabela, na cor da tabela.
     */
    public static function icone(MdGerenciamento $gerenciamento): string|HtmlString
    {
        $tabela = $gerenciamento->tabela;

        return MdTabela::iconeColorido($gerenciamento->icone ?: ($tabela->icone ?: 'heroicon-o-table-cells'), $tabela->cor);
    }

    /**
     * Itens avulsos dos módulos para a tela Navegação: nenhum (as telas entram como qualquer resource do painel).
     *
     * @return array<string, array{label: string, group: ?string, icon: ?string, sort: int}>
     */
    public static function catalogo(string $painel): array
    {
        return [];
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
