<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Prepara uma cópia nova do template: nome, endereço e banco no .env, chave da
 * aplicação, dicionário vazio.
 */
class TemplateInitCommand extends Command
{
    protected $signature = 'template:init
        {nome : Nome do projeto (ex.: "Academia Forte")}
        {--dominio= : Domínio central (padrão: slug do nome + .test)}
        {--banco= : Nome do banco central (padrão: slug do nome com _)}';

    protected $description = 'Configura uma cópia nova do template (.env, chave e dicionário vazio)';

    public function handle(): int
    {
        $nome = (string) $this->argument('nome');
        $slug = Str::slug($nome);
        $dominio = $this->option('dominio') ?: "{$slug}.test";
        $banco = $this->option('banco') ?: Str::slug($nome, '_');

        if (! is_file(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }

        $this->definir('APP_NAME', '"'.str_replace('"', '', $nome).'"');
        $this->definir('APP_URL', "http://{$dominio}");
        $this->definir('DB_DATABASE', $banco);

        $this->call('key:generate', ['--force' => false]);
        $this->call('config:clear');

        // o núcleo é só o que o sistema precisa; módulos e tabelas são do projeto
        file_put_contents(database_path('md/modulos.json'), "[]\n");

        $this->info("Projeto \"{$nome}\" preparado.");
        $this->line("  Domínio central: http://{$dominio}");
        $this->line("  Banco central:   {$banco} (crie-o no PostgreSQL)");
        $this->newLine();
        $this->line('Próximos passos:');
        $this->line('  php artisan migrate');
        $this->line('  php artisan shield:super-admin');
        $this->line('  php artisan shield:generate --all');
        $this->line('  php artisan md:importar');

        return self::SUCCESS;
    }

    protected function definir(string $chave, string $valor): void
    {
        $arquivo = base_path('.env');
        $conteudo = file_get_contents($arquivo);
        $linha = "{$chave}={$valor}";

        $conteudo = preg_match("/^{$chave}=.*$/m", $conteudo)
            ? preg_replace("/^{$chave}=.*$/m", str_replace('\\', '\\\\', $linha), $conteudo)
            : rtrim($conteudo)."\n{$linha}\n";

        file_put_contents($arquivo, $conteudo);
    }
}
