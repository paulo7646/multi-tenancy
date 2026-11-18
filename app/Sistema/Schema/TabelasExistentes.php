<?php

namespace App\Sistema\Schema;

use App\Models\Filial;
use App\Models\Licenca;
use App\Models\User;
use App\Plataforma\Models\Configuracao;
use App\Webservice\Models\Agendamento;
use App\Webservice\Models\Atributo;
use App\Webservice\Models\Busca;
use App\Webservice\Models\Evento;
use App\Webservice\Models\Integracao;
use App\Webservice\Models\Lote;
use App\Webservice\Models\Objeto;
use App\Webservice\Models\Operacao;
use App\Webservice\Models\Parametro;
use App\Webservice\Models\Versao;
use App\Webservice\Models\Webservice;
use OwenIt\Auditing\Models\Audit;

/**
 * Tabelas feitas à mão que o dicionário passa a conhecer (comando md:incorporar), por módulo de clientes.
 * A ordem importa: uma tabela só vira FK de outra depois de cadastrada.
 *
 * Estrutura é o módulo padrão (todo cliente tem); Webservice só vale para as empresas que o têm.
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
                    ['tabela' => 'plt_configuracoes', 'classe_model' => Configuracao::class, 'nome' => 'Configurações da empresa'],
                    ['tabela' => 'audits', 'classe_model' => Audit::class, 'nome' => 'Auditoria'],
                ],
            ],
            [
                'modulo' => [
                    'prefixo' => 'ws', 'nome' => 'Webservice', 'pasta' => 'Webservice', 'padrao' => false,
                    'descricao' => 'Webservices consumidos ou disponibilizados e layouts de integração.',
                ],
                'tabelas' => [
                    ['tabela' => 'webservices', 'classe_model' => Webservice::class, 'nome' => 'Webservices', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'ws_operacoes', 'classe_model' => Operacao::class, 'nome' => 'Operações', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'ws_parametros', 'classe_model' => Parametro::class, 'nome' => 'Parâmetros', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'ws_versoes', 'classe_model' => Versao::class, 'nome' => 'Versões do layout', 'campo_exibicao' => 'versao'],
                    ['tabela' => 'ws_objetos', 'classe_model' => Objeto::class, 'nome' => 'Objetos do retorno', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'ws_eventos', 'classe_model' => Evento::class, 'nome' => 'Eventos', 'campo_exibicao' => 'nome'],
                    ['tabela' => 'ws_evento_busca', 'classe_model' => Busca::class, 'nome' => 'Busca dos eventos'],
                    ['tabela' => 'ws_atributos', 'classe_model' => Atributo::class, 'nome' => 'Atributos'],
                    ['tabela' => 'ws_integracoes', 'classe_model' => Integracao::class, 'nome' => 'Integrações'],
                    ['tabela' => 'ws_lotes', 'classe_model' => Lote::class, 'nome' => 'Lotes'],
                    ['tabela' => 'ws_agendamentos', 'classe_model' => Agendamento::class, 'nome' => 'Agendamentos', 'campo_exibicao' => 'nome'],
                ],
            ],
        ];
    }
}
