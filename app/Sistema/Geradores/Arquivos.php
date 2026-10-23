<?php

namespace App\Sistema\Geradores;

use Illuminate\Support\Facades\File;

/**
 * Escrita dos arquivos gerados.
 */
class Arquivos
{
    public static function gravar(string $path, string $conteudo): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $conteudo);
    }

    /**
     * Cria só se não existir (arquivos que você personaliza).
     */
    public static function gravarSeNaoExistir(string $path, string $conteudo): bool
    {
        if (is_file($path)) {
            return false;
        }

        static::gravar($path, $conteudo);

        return true;
    }

    public static function apagar(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (is_file($path)) {
                File::delete($path);
            }
        }
    }

    public static function relativo(string $path): string
    {
        return str_replace([base_path() . DIRECTORY_SEPARATOR, base_path() . '/'], '', $path);
    }

    /**
     * Valor PHP literal (arrays em uma linha).
     */
    public static function exportar(mixed $valor): string
    {
        if (is_array($valor)) {
            $isList = array_is_list($valor);

            return '[' . implode(', ', array_map(
                fn ($item, $key) => ($isList ? '' : var_export($key, true) . ' => ') . static::exportar($item),
                $valor,
                array_keys($valor),
            )) . ']';
        }

        return var_export($valor, true);
    }
}
