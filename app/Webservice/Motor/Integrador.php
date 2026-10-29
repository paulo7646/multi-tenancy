<?php

namespace App\Webservice\Motor;

use App\Webservice\Models\Integracao;
use App\Webservice\Models\Operacao;
use App\Webservice\Models\Versao;
use Throwable;

/**
 * Cria a integração de uma operação: busca (ou recebe) o conteúdo, guarda o
 * arquivo e processa com a versão vigente do layout.
 */
class Integrador
{
    /**
     * Chama a URL da operação e processa o retorno ("Executar").
     *
     * @param  array<string, mixed>  $valores  valores dos parâmetros sem valor fixo
     */
    public static function executar(Operacao $operacao, array $valores = [], string $origem = 'manual', ?int $agendamentoId = null): Integracao
    {
        $integracao = static::abrir($operacao, $origem, $valores, $agendamentoId);

        try {
            $conteudo = Cliente::buscar($operacao, $valores);
            $integracao->guardarArquivo($conteudo);
            static::processar($integracao, $operacao, $conteudo);
        } catch (Throwable $e) {
            static::falhar($integracao, $e);
        }

        return $integracao->refresh();
    }

    /**
     * Processa o conteúdo que um terceiro enviou para um webservice disponibilizado.
     */
    public static function receber(Operacao $operacao, string $conteudo): Integracao
    {
        $integracao = static::abrir($operacao, 'recebida', [], null);

        try {
            $integracao->guardarArquivo($conteudo);
            static::processar($integracao, $operacao, $conteudo);
        } catch (Throwable $e) {
            static::falhar($integracao, $e);
        }

        return $integracao->refresh();
    }

    protected static function abrir(Operacao $operacao, string $origem, array $valores, ?int $agendamentoId): Integracao
    {
        return Integracao::create([
            'operacao_id' => $operacao->getKey(),
            'agendamento_id' => $agendamentoId,
            'origem' => $origem,
            'status' => 'processando',
            'formato' => $operacao->formato,
            'parametros' => $valores ?: null,
            'iniciada_em' => now(),
        ]);
    }

    protected static function processar(Integracao $integracao, Operacao $operacao, string $conteudo): void
    {
        $versao = static::versaoVigente($operacao);
        $integracao->update(['versao_id' => $versao->getKey()]);

        $status = (new Processador($integracao, $versao))->processar(Leitor::paraArray($conteudo, $operacao->formato));

        $integracao->update([
            'status' => $status,
            'finalizada_em' => now(),
            'erro' => $status === 'concluida' ? null : 'Há lotes com erro: veja a aba Lotes.',
        ]);
    }

    protected static function versaoVigente(Operacao $operacao): Versao
    {
        return $operacao->versoes()->orderByDesc('id')->get()->first(fn (Versao $v) => $v->vigente())
            ?? throw new ProcessamentoException('A operação não tem versão de layout vigente (ativa e dentro da vigência).');
    }

    protected static function falhar(Integracao $integracao, Throwable $e): void
    {
        report($e);

        $integracao->update([
            'status' => 'erro',
            'erro' => $e->getMessage(),
            'finalizada_em' => now(),
        ]);
    }
}
