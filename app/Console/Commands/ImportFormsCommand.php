<?php

namespace App\Console\Commands;

use App\Forms\FormExporter;
use Illuminate\Console\Command;

class ImportFormsCommand extends Command
{
    protected $signature = 'forms:import';

    protected $description = 'Recria o cadastro do construtor de formulários a partir de database/forms/*.json (banco local novo, outra máquina)';

    public function handle(): int
    {
        foreach (FormExporter::import() as $form) {
            $this->info("Importado: {$form}");
        }

        $this->comment('As tabelas vêm das migrations: rode php artisan migrate e php artisan tenants:migrate.');

        return self::SUCCESS;
    }
}
