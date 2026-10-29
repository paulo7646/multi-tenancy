<?php

namespace App\Webservice\Motor;

use App\Models\Md\MdTabela;
use App\Webservice\Contexto;
use App\Webservice\Models\Agendamento;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Roda os agendamentos que venceram, no banco atual (central ou o do cliente
 * inicializado). O comando webservice:agendamentos chama isto para a base pai e
 * para cada cliente.
 */
class Agendador
{
    /**
     * @return int quantos agendamentos rodaram
     */
    public static function rodarPendentes(): int
    {
        if (! Schema::hasTable('ws_agendamentos')) {
            return 0;
        }

        // expirados saem de cena
        Agendamento::where('ativo', true)->whereNotNull('expira_em')->where('expira_em', '<=', now())
            ->get()->each(fn (Agendamento $a) => $a->update(['ativo' => false, 'proxima_execucao' => null]));

        $rodados = 0;

        Agendamento::where('ativo', true)->whereNotNull('proxima_execucao')->where('proxima_execucao', '<=', now())
            ->get()
            ->each(function (Agendamento $agendamento) use (&$rodados) {
                static::executar($agendamento);
                $rodados++;
            });

        return $rodados;
    }

    /**
     * Roda um agendamento agora e agenda a próxima execução.
     */
    public static function executar(Agendamento $agendamento): void
    {
        try {
            match ($agendamento->tipo) {
                'layout_edi' => static::layout($agendamento),
                default => static::codigoFonte($agendamento),
            };

            $agendamento->forceFill(['ultimo_status' => 'ok', 'ultimo_erro' => null]);
        } catch (Throwable $e) {
            report($e);
            $agendamento->forceFill(['ultimo_status' => 'erro', 'ultimo_erro' => $e->getMessage()]);
        }

        $agendamento->forceFill([
            'ultima_execucao' => now(),
            'proxima_execucao' => $agendamento->calcularProxima(),
        ])->save();
    }

    protected static function layout(Agendamento $agendamento): void
    {
        $operacao = $agendamento->operacao ?? throw new ProcessamentoException('O agendamento não tem operação.');

        $integracao = Integrador::executar($operacao, $agendamento->parametros ?? [], 'agendamento', $agendamento->getKey());

        if ($integracao->status === 'erro') {
            throw new ProcessamentoException($integracao->erro ?: 'A integração falhou.');
        }
    }

    protected static function codigoFonte(Agendamento $agendamento): void
    {
        $tabela = MdTabela::where('banco', Contexto::banco()->value)->where('nome_fisico', $agendamento->tabela)->first()
            ?? throw new ProcessamentoException("A tabela \"{$agendamento->tabela}\" não existe no dicionário.");

        $classe = $tabela->controllerFqcn();

        if (! class_exists($classe) || blank($agendamento->funcao) || ! method_exists($classe, $agendamento->funcao)) {
            throw new ProcessamentoException("A função \"{$agendamento->funcao}\" não existe em {$classe}.");
        }

        (new $classe)->{$agendamento->funcao}($agendamento);
    }
}
