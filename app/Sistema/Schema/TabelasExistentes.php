<?php

namespace App\Sistema\Schema;

use App\Models\Filial;
use App\Models\Licenca;
use App\Models\User;
use OwenIt\Auditing\Models\Audit;

/**
 * Tabelas feitas à mão que o dicionário passa a conhecer (comando md:incorporar), por módulo de clientes.
 * A ordem importa: uma tabela só vira FK de outra depois de cadastrada.
 *
 * Estrutura é o módulo padrão (todo cliente tem).
 * `users` existe também na base pai, mas o dicionário guarda uma tabela por nome físico: fica a dos clientes.
 */
class TabelasExistentes
{
    /**
     * @return list<array{modulo: array<string, mixed>, tabelas: list<array{tabela: string, classe_model: class-string, nome: string, campo_exibicao?: string}>}>
     */
    public static function manifesto(): array
    {
        return [
            [
                'modulo' => [
                    'prefixo' => 'est', 'nome' => 'Estrutura', 'pasta' => 'Estrutura', 'padrao' => true,
                    'descricao' => 'Cadastros padrão que todos os clientes têm.',
                ],
                'tabelas' => [
                    ['tabela' => 'licencas', 'classe_model' => Licenca::class, 'nome' => 'Licenças', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'filiais', 'classe_model' => Filial::class, 'nome' => 'Filiais', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'users', 'classe_model' => User::class, 'nome' => 'Usuários', 'campo_exibicao' => 'name'],
                    ['tabela' => 'audits', 'classe_model' => Audit::class, 'nome' => 'Auditoria'],
                ],
            ],
        ];
    }
}
