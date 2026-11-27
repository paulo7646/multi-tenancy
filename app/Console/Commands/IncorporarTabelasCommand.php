<?php

namespace App\Console\Commands;

use App\Models\Md\Modulo;
use App\Models\Tenant;
use App\Sistema\Enums\Banco;
use App\Sistema\Geradores\Exportador;
use App\Sistema\Schema\Incorporador;
use App\Sistema\Schema\TabelasExistentes;
use Illuminate\Console\Command;
use Throwable;

class IncorporarTabelasCommand extends Command
{
    protected $signature = 'md:incorporar {--sem-exportar : não grava o dicionário em database/md}';

    protected $description = 'Cadastra no dicionário as tabelas feitas à mão (módulo Estrutura), sem gerar migration nem arquivos';

    public function handle(): int
    {
        $empresa = Tenant::query()->first();

        if (! $empresa) {
            $this->error('Cadastre ao menos uma empresa: a estrutura das tabelas de clientes é lida do banco dela.');

            return self::FAILURE;
        }

        $tabelas = [];

        foreach (TabelasExistentes::manifesto() as $grupo) {
            $modulo = Modulo::updateOrCreate(['prefixo' => $grupo['modulo']['prefixo']], [...$grupo['modulo'], 'banco' => Banco::Tenant]);
            $this->info("Módulo {$modulo->nome}".($modulo->padrao ? ' (padrão)' : ''));

            foreach ($grupo['tabelas'] as $dados) {
                try {
                    $tabela = Incorporador::incorporar($modulo, $dados['tabela'], $dados, $empresa);
                } catch (Throwable $e) {
                    $this->error("  {$dados['tabela']}: {$e->getMessage()}");

                    return self::FAILURE;
                }

                $tabelas[] = $tabela;
                $this->line("  {$tabela->nome_fisico}: {$tabela->campos()->count()} campo(s)");
            }
        }

        if (! $this->option('sem-exportar')) {
            Exportador::modulos();

            foreach ($tabelas as $tabela) {
                Exportador::tabela($tabela);
            }

            $this->comment('Dicionário gravado em database/md.');
        }

        return self::SUCCESS;
    }
}
