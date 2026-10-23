<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdCampo;
use App\Models\Md\MdTabela;
use App\Sistema\Enums\Banco;
use App\Sistema\Enums\TipoCampo;
use RuntimeException;

/**
 * Gera o model da tabela (app/Modulos/{modulo}/{tabela}/{Model}.php).
 *
 * O arquivo é seu (eventos, regras, escopos); só o bloco entre os marcadores
 * <estrutura> é recriado sempre que a tabela é salva no dicionário.
 */
class ModelWriter
{
    public const INICIO = '// <estrutura> gerado pelo dicionário de dados: não edite este bloco';
    public const FIM = '// </estrutura>';

    public static function path(MdTabela $tabela): string
    {
        return $tabela->caminho($tabela->classeModel() . '.php');
    }

    public static function gravar(MdTabela $tabela): void
    {
        $path = static::path($tabela);
        $estrutura = static::estrutura($tabela);

        if (! is_file($path)) {
            Arquivos::gravar($path, static::esqueleto($tabela, $estrutura));

            return;
        }

        $codigo = file_get_contents($path);
        $padrao = '/' . preg_quote(static::INICIO, '/') . '.*?' . preg_quote(static::FIM, '/') . '/s';

        if (! preg_match($padrao, $codigo)) {
            throw new RuntimeException('Os marcadores <estrutura> foram removidos de ' . Arquivos::relativo($path) . '. Restaure-os para o dicionário conseguir atualizar o model.');
        }

        Arquivos::gravar($path, preg_replace_callback($padrao, fn () => $estrutura, $codigo, 1));
    }

    public static function apagar(MdTabela $tabela): void
    {
        Arquivos::apagar(static::path($tabela));
    }

    protected static function estrutura(MdTabela $tabela): string
    {
        $tabela->loadMissing(['campos.fkTabela.modulo']);
        $campos = $tabela->campos;
        $lista = fn (array $valores) => $valores === []
            ? '[]'
            : "[\n" . implode("\n", array_map(fn ($v) => "        '{$v}',", $valores)) . "\n    ]";

        $fillable = $campos->pluck('nome_fisico')->all();
        if ($tabela->controla_situacao) {
            $fillable[] = MdTabela::COLUNA_SITUACAO;
        }

        $hidden = $campos->filter(fn (MdCampo $c) => $c->tipo === TipoCampo::Password)->pluck('nome_fisico')->all();

        $casts = $campos
            ->filter(fn (MdCampo $c) => $c->tipo->cast($c->escala) !== null)
            ->map(fn (MdCampo $c) => "            '{$c->nome_fisico}' => '{$c->tipo->cast($c->escala)}',")
            ->implode("\n");

        $relacoes = $campos
            ->filter(fn (MdCampo $c) => $c->isFk() && $c->fkTabela)
            ->map(fn (MdCampo $c) => <<<PHP

    public function {$c->nomeRelacao()}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo
    {
        return \$this->belongsTo(\\{$c->fkTabela->modelFqcn()}::class, '{$c->nome_fisico}');
    }
PHP)
            ->implode("\n");

        $conexao = $tabela->banco === Banco::Central ? "\n    protected \$connection = 'central';\n" : '';
        $padrao = $tabela->situacaoPadrao();
        $coluna = MdTabela::COLUNA_SITUACAO;
        $atributos = $padrao ? "\n    protected \$attributes = ['{$coluna}' => '{$padrao}'];\n" : '';

        return static::INICIO . <<<PHP

    protected static string \$mdTabela = '{$tabela->nome_fisico}';
{$conexao}
    protected \$table = '{$tabela->nome_fisico}';

    protected \$fillable = {$lista($fillable)};

    protected \$hidden = {$lista($hidden)};
{$atributos}
    protected function casts(): array
    {
        return [
{$casts}
        ];
    }
{$relacoes}
PHP . "\n    " . static::FIM;
    }

    protected static function esqueleto(MdTabela $tabela, string $estrutura): string
    {
        return <<<PHP
<?php

namespace {$tabela->namespace()};

use {$tabela->modulo->namespace()}\Enums;
use App\Sistema\Base\BaseModel;

/**
 * Tabela "{$tabela->nome}" ({$tabela->nome_fisico}) do módulo {$tabela->modulo->nome}.
 *
 * Criado pelo dicionário de dados. Escreva aqui os eventos (beforePost,
 * afterPost, afterAfterPost, beforeDelete...) e as regras da tabela; veja
 * App\Sistema\Base\BaseModel. Só o bloco <estrutura> é recriado.
 */
class {$tabela->classeModel()} extends BaseModel
{
    {$estrutura}
}

PHP;
    }
}
