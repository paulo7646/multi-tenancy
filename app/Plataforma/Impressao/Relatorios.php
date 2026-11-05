<?php

namespace App\Plataforma\Impressao;

use App\Models\Md\MdTabela;
use App\Plataforma\Models\Configuracao;
use App\Plataforma\Models\ModeloImpressao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Gera os PDFs: lista de uma tabela e ficha de um registro, com o cabeçalho da
 * empresa e o modelo de impressão escolhido (ou o layout automático).
 */
class Relatorios
{
    /**
     * @param  iterable<Model>  $registros
     */
    public static function lista(MdTabela $tabela, iterable $registros, ?ModeloImpressao $modelo = null, ?string $filtros = null): string
    {
        $tabela->loadMissing(['situacoes.status']);
        $colunas = Valores::colunas($tabela);

        $linhas = [];
        foreach ($registros as $registro) {
            $linhas[] = array_map(fn (array $c) => Valores::de($registro, $c[1], $tabela), $colunas);
        }

        return static::pdf('plataforma.impressao.lista', $modelo, [
            'titulo' => $modelo?->titulo ?: $tabela->nome,
            'cabecalhos' => array_column($colunas, 0),
            'linhas' => $linhas,
            'filtros' => $filtros,
        ], $modelo?->orientacao ?? (count($colunas) > 6 ? 'landscape' : 'portrait'));
    }

    public static function registro(MdTabela $tabela, Model $registro, ?ModeloImpressao $modelo = null): string
    {
        $tabela->loadMissing(['situacoes.status']);
        $colunas = Valores::colunas($tabela);

        $dados = [];
        foreach ($colunas as [$rotulo, $coluna]) {
            $dados[] = [$rotulo, Valores::de($registro, $coluna, $tabela), is_object($coluna) ? $coluna->nome_fisico : strtoupper($coluna)];
        }

        $corpo = $modelo?->corpo ? static::corpoComMarcadores($modelo->corpo, $dados) : null;

        return static::pdf('plataforma.impressao.registro', $modelo, [
            'titulo' => $modelo?->titulo ?: "{$tabela->nome} #{$registro->getKey()}",
            'dados' => $dados,
            'corpo' => $corpo,
        ], $modelo?->orientacao ?? 'portrait');
    }

    /**
     * Troca os marcadores {CAMPO} pelos valores (sempre escapados) depois de tirar do
     * modelo qualquer HTML fora da lista permitida.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $dados  [rótulo, valor, nome do campo]
     */
    public static function corpoComMarcadores(string $corpo, array $dados): HtmlString
    {
        $limpo = strip_tags($corpo, ModeloImpressao::HTML_PERMITIDO);

        $valores = [
            'EMPRESA' => Configuracao::empresa()['nome'],
            'DATA' => now()->format('d/m/Y'),
            'HORA' => now()->format('H:i'),
            'USUARIO' => (string) auth()->user()?->name,
        ];

        foreach ($dados as [, $valor, $campo]) {
            $valores[strtoupper($campo)] = $valor;
        }

        $html = preg_replace_callback(
            '/\{([A-Za-z0-9_]+)\}/',
            fn (array $m) => array_key_exists(strtoupper($m[1]), $valores) ? e($valores[strtoupper($m[1])]) : $m[0],
            $limpo,
        );

        return new HtmlString($html);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    protected static function pdf(string $visao, ?ModeloImpressao $modelo, array $dados, string $orientacao): string
    {
        $empresa = Configuracao::empresa();

        return Pdf::loadView($visao, [
            ...$dados,
            'empresa' => $empresa,
            'logo' => static::logo($empresa['logo']),
            'cabecalho' => $modelo?->mostrar_cabecalho ?? true,
            'rodape' => $modelo?->rodape ?: $empresa['rodape'],
            'emitidoPor' => auth()->user()?->name,
        ])->setPaper($modelo?->papel ?? 'a4', $orientacao)->output();
    }

    /**
     * O logo da empresa como imagem embutida (o PDF não busca nada na rede).
     */
    protected static function logo(?string $caminho): ?string
    {
        if (blank($caminho)) {
            return null;
        }

        $disco = Storage::disk('local');

        if (! $disco->exists($caminho)) {
            return null;
        }

        $conteudo = $disco->get($caminho);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($conteudo);

        return str_starts_with((string) $mime, 'image/') ? 'data:'.$mime.';base64,'.base64_encode($conteudo) : null;
    }
}
