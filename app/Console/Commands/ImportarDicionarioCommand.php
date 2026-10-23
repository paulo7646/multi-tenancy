<?php

namespace App\Console\Commands;

use App\Sistema\Geradores\Exportador;
use Illuminate\Console\Command;

class ImportarDicionarioCommand extends Command
{
    protected $signature = 'md:importar';

    protected $description = 'Recria o dicionário de dados (módulos, tabelas, formulários, gerenciamentos) a partir de database/md/*.json';

    public function handle(): int
    {
        foreach (Exportador::importar() as $linha) {
            $this->info("Importado: {$linha}");
        }

        $this->comment('As tabelas vêm das migrations: rode php artisan migrate e php artisan tenants:migrate.');

        return self::SUCCESS;
    }
}
