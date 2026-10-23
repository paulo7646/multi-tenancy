<?php

namespace App\Sistema\Runtime;

/**
 * Árvore do formulário (montada de md_formulario_componentes):
 *
 *   [{titulo, campos: [elemento, ...]}]
 *
 * Elemento:
 *   {tipo: 'campo', campo: uid|'situacao', componente?, traducao_id?, colunas,
 *    quebra?, completar?, obrigatorio?, largura?, altura?}
 *   {tipo: 'grupo', titulo, colunasGrupo, colunas, quebra?, completar?, largura?, altura?, campos: [campo]}
 *
 * componente = como o campo aparece (App\Sistema\Enums\TipoComponente);
 * quebra = começa numa linha nova; completar = alinhar pela sugestão (até o
 * fim da linha); obrigatorio = null segue o campo; largura/altura em pixels.
 */
class Layout
{
    public const CAMPO = 'campo';
    public const GRUPO = 'grupo';

    /**
     * Todos os campos (uids) usados, inclusive dentro dos grupos.
     *
     * @return list<string>
     */
    public static function uids(?array $layout): array
    {
        $uids = [];

        foreach ($layout ?? [] as $pagina) {
            foreach (static::elementos($pagina['campos'] ?? []) as $elemento) {
                if ($elemento['tipo'] === static::GRUPO) {
                    foreach (static::elementos($elemento['campos'] ?? []) as $filho) {
                        if (filled($filho['campo'] ?? null)) {
                            $uids[] = $filho['campo'];
                        }
                    }
                } elseif (filled($elemento['campo'] ?? null)) {
                    $uids[] = $elemento['campo'];
                }
            }
        }

        return array_values(array_unique($uids));
    }

    /**
     * Elementos com o tipo preenchido ("memo" antigo vira campo com componente memo).
     *
     * @return list<array<string, mixed>>
     */
    public static function elementos(array $elementos): array
    {
        return array_values(array_map(function (array $e) {
            if (($e['tipo'] ?? null) === 'memo') {
                return [...$e, 'tipo' => static::CAMPO, 'componente' => $e['componente'] ?? 'memo'];
            }

            return [...$e, 'tipo' => ($e['tipo'] ?? null) === static::GRUPO ? static::GRUPO : static::CAMPO];
        }, array_filter($elementos, 'is_array')));
    }

    /**
     * Largura/altura válidas (px): número inteiro positivo, ou null.
     */
    public static function pixels(mixed $valor): ?int
    {
        return is_numeric($valor) && (int) $valor > 0 ? min((int) $valor, 4000) : null;
    }
}
