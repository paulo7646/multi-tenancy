<?php

namespace App\Forms\Generators;

use Illuminate\Support\Facades\File;

/**
 * Escrita dos arquivos gerados.
 */
class Files
{
    public static function put(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    /**
     * Cria só se não existir (arquivos que você personaliza).
     */
    public static function putIfMissing(string $path, string $contents): void
    {
        if (! is_file($path)) {
            static::put($path, $contents);
        }
    }

    public static function delete(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            } elseif (is_file($path)) {
                File::delete($path);
            }
        }
    }
}
