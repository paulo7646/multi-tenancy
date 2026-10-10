<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Gerencia as pastas Vue de cada empresa em views/clientes/{slug}.
 */
class ClienteFrontend
{
    public const STUBS = ['main.js', 'App.vue', 'theme.js'];

    public static function path(string $slug, string $file = ''): string
    {
        return base_path(trim("views/clientes/{$slug}/{$file}", '/'));
    }

    public static function entry(string $slug): string
    {
        return "views/clientes/{$slug}/main.js";
    }

    public static function exists(string $slug): bool
    {
        return File::exists(static::path($slug, 'main.js'));
    }

    /**
     * Cria a pasta da empresa a partir de stubs/cliente. Arquivos já
     * existentes nunca são sobrescritos.
     */
    public static function scaffold(string $slug): void
    {
        File::ensureDirectoryExists(static::path($slug));

        foreach (static::STUBS as $file) {
            $target = static::path($slug, $file);

            if (File::exists($target)) {
                continue;
            }

            $content = str_replace(
                '{{ slug }}',
                $slug,
                File::get(base_path("stubs/cliente/{$file}.stub"))
            );

            File::put($target, $content);
        }
    }
}
