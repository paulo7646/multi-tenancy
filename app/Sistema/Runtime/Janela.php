<?php

namespace App\Sistema\Runtime;

/**
 * Telas abertas como "janela" (iframe com X e − na barra inferior, ver components/janelas-gerenciador).
 * O modo vem na URL (janela, modo, campo, recurso, filtro_id) e, nas atualizações do Livewire, que perdem
 * a query, do Referer da própria página. Só muda a interface: nada aqui libera dado.
 */
class Janela
{
    /** Quantas janelas cabem na barra; a mais antiga fecha quando entra a seguinte. */
    public const LIMITE = 5;

    public static function ativa(): bool
    {
        return static::param('janela') === '1';
    }

    /** "selecionar": a lista devolve o registro escolhido ao campo que abriu a janela. */
    public static function modo(): ?string
    {
        return static::param('modo');
    }

    /** Caminho do estado do campo (Livewire) que espera o registro escolhido. */
    public static function campo(): ?string
    {
        return static::param('campo');
    }

    /** Resource (sujeito das permissões) para o qual o modo e o filtro valem. */
    public static function recurso(): ?string
    {
        return static::param('recurso');
    }

    /** Mostra só este registro (a seta do campo de FK abre a lista filtrada nele). */
    public static function filtroId(): ?string
    {
        return static::param('filtro_id');
    }

    /**
     * @param  array<string, scalar|null>  $parametros
     */
    public static function url(string $base, array $parametros = []): string
    {
        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(['janela' => 1, ...array_filter($parametros, fn ($v) => $v !== null)]);
    }

    /** JS que abre a janela no gerenciador da página. */
    public static function scriptAbrir(string $titulo, string $url): string
    {
        return 'window.Janelas && window.Janelas.abrir('.static::json(['titulo' => $titulo, 'url' => $url]).')';
    }

    /** JS (dentro da janela) que devolve o registro escolhido à página que abriu a janela. */
    public static function scriptSelecionar(string $campo, mixed $id): string
    {
        return 'window.parent.postMessage('.static::json(['tipo' => 'fk-selecionado', 'campo' => $campo, 'id' => $id]).', window.location.origin)';
    }

    protected static function json(array $dados): string
    {
        return json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    protected static function param(string $chave): ?string
    {
        $valor = request()->query($chave);

        if ($valor === null && ($referer = request()->headers->get('referer')) && parse_url($referer, PHP_URL_HOST) === request()->getHost()) {
            parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
            $valor = $query[$chave] ?? null;
        }

        return is_scalar($valor) ? (string) $valor : null;
    }
}
