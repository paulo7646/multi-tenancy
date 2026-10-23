<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdTabela;
use App\Models\Md\MdTabelaItem;
use App\Sistema\Enums\TipoTabela;

/**
 * Gera o enum das tabelas lista/conteúdo em app/Modulos/{Modulo}/Enums/,
 * acessível a todos os arquivos do módulo (use App\Modulos\{Modulo}\Enums;
 * Enums\StatusPedido::ABERTO). Recriado sempre que os itens mudam.
 */
class EnumWriter
{
    public static function gravar(MdTabela $tabela): void
    {
        $tabela->loadMissing(['modulo', 'itens']);
        $lista = $tabela->tipoTabela() === TipoTabela::Lista;
        $tipo = $lista ? 'int' : 'string';
        $usados = [];

        $casos = $tabela->itens->map(function (MdTabelaItem $item) use ($lista, &$usados) {
            $nome = MdTabelaItem::nomeDoCaso($item->descricao);

            for ($n = 2; in_array($nome, $usados, true); $n++) {
                $nome = MdTabelaItem::nomeDoCaso($item->descricao) . "_{$n}";
            }
            $usados[] = $nome;

            return [
                'nome' => $nome,
                'valor' => $lista ? (int) $item->codigo : (string) $item->valor,
                'descricao' => $item->descricao,
            ];
        });

        $exportar = fn ($v) => var_export($v, true);

        $declaracoes = $casos->map(fn ($c) => "    case {$c['nome']} = {$exportar($c['valor'])};")->implode("\n");
        $rotulos = $casos->map(fn ($c) => "            {$exportar($c['valor'])} => {$exportar($c['descricao'])},")->implode("\n");

        $namespace = $tabela->modulo->namespace() . '\\Enums';
        $classe = $tabela->nomeClasse();
        $origem = $lista ? 'código sequencial' : 'valor definido no cadastro';

        Arquivos::gravar($tabela->enumPath(), <<<PHP
<?php

namespace {$namespace};

use Filament\Support\Contracts\HasLabel;

/**
 * Itens da tabela "{$tabela->nome}" ({$tabela->nome_fisico}), {$origem}.
 *
 * GERADO pelo dicionário de dados: recriado sempre que os itens mudam.
 * Em qualquer arquivo do módulo: use {$namespace}; ... Enums\\{$classe}::CASO.
 */
enum {$classe}: {$tipo} implements HasLabel
{
{$declaracoes}

    public function getLabel(): string
    {
        return static::rotulos()[\$this->value];
    }

    /**
     * @return array<{$tipo}, string> valor => descrição
     */
    public static function rotulos(): array
    {
        return [
{$rotulos}
        ];
    }

    /**
     * Opções para combos: valor => descrição.
     *
     * @return array<{$tipo}, string>
     */
    public static function opcoes(): array
    {
        return static::rotulos();
    }
}

PHP);
    }

    public static function apagar(MdTabela $tabela): void
    {
        Arquivos::apagar($tabela->enumPath());
    }
}
