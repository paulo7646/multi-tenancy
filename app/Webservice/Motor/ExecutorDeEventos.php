<?php

namespace App\Webservice\Motor;

use App\Models\Md\MdTabela;
use App\Sistema\Registro;
use App\Webservice\Contexto;
use App\Webservice\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Executa um evento do objeto para um item do retorno: grava na tabela (pelo
 * model, com as regras e eventos dele) ou chama a função do controller.
 */
class ExecutorDeEventos
{
    public static function executar(Evento $evento, ContextoEvento $contexto): void
    {
        $tabela = MdTabela::where('banco', Contexto::banco()->value)->where('nome_fisico', $evento->tabela)->first()
            ?? throw new ProcessamentoException("A tabela \"{$evento->tabela}\" do evento não existe no dicionário.");

        if ($evento->tipo === 'codigo_fonte') {
            static::codigoFonte($tabela, $evento, $contexto);

            return;
        }

        $model = Registro::model($tabela);

        match ($evento->tipo) {
            'incluir' => static::incluir($model, $evento, $contexto),
            'incluir_se_nao_existir' => static::incluirSeNaoExistir($model, $evento, $contexto),
            'alterar' => static::alterar($model, $evento, $contexto),
            'excluir' => static::excluir($model, $evento, $contexto),
            default => throw new ProcessamentoException("Tipo de evento desconhecido: {$evento->tipo}."),
        };
    }

    protected static function codigoFonte(MdTabela $tabela, Evento $evento, ContextoEvento $contexto): void
    {
        $classe = $tabela->controllerFqcn();

        if (! class_exists($classe) || blank($evento->funcao) || ! method_exists($classe, $evento->funcao)) {
            throw new ProcessamentoException("A função \"{$evento->funcao}\" não existe em {$classe}.");
        }

        (new $classe)->{$evento->funcao}($contexto);
    }

    protected static function incluir(Model $model, Evento $evento, ContextoEvento $contexto): void
    {
        $novo = $model->newInstance();
        $novo->forceFill(static::valores($evento, $contexto))->save();
    }

    protected static function incluirSeNaoExistir(Model $model, Evento $evento, ContextoEvento $contexto): void
    {
        static::exigirBusca($evento);

        if (! static::consulta($model, $evento, $contexto)->exists()) {
            static::incluir($model, $evento, $contexto);
        }
    }

    protected static function alterar(Model $model, Evento $evento, ContextoEvento $contexto): void
    {
        static::exigirBusca($evento);
        $registros = static::consulta($model, $evento, $contexto)->get();

        if ($registros->isEmpty()) {
            throw new ProcessamentoException("Alterar: nenhum registro de {$evento->tabela} encontrado pelos campos de busca.");
        }

        $valores = static::valores($evento, $contexto);

        foreach ($registros as $registro) {
            $registro->forceFill($valores)->save();
        }
    }

    protected static function excluir(Model $model, Evento $evento, ContextoEvento $contexto): void
    {
        static::exigirBusca($evento);

        foreach (static::consulta($model, $evento, $contexto)->get() as $registro) {
            $registro->delete();
        }
    }

    protected static function exigirBusca(Evento $evento): void
    {
        if ($evento->busca->isEmpty()) {
            throw new ProcessamentoException("O evento \"{$evento->tipo}\" em {$evento->tabela} precisa de campos de busca.");
        }
    }

    /**
     * Colunas = atributos do item (mapeados em Atributos). Atributo ausente no retorno fica de fora.
     *
     * @return array<string, mixed>
     */
    protected static function valores(Evento $evento, ContextoEvento $contexto): array
    {
        $valores = [];

        foreach ($evento->atributos as $atributo) {
            if (array_key_exists($atributo->nome_json, $contexto->dados)) {
                $valor = $contexto->dados[$atributo->nome_json];
                $valores[$atributo->campo] = is_array($valor) ? json_encode($valor) : $valor;
            }
        }

        return $valores;
    }

    /**
     * Campos de busca: campo operador valor. O valor pode citar atributos do retorno: {id}.
     */
    protected static function consulta(Model $model, Evento $evento, ContextoEvento $contexto): Builder
    {
        $consulta = $model->newQuery();

        foreach ($evento->busca as $condicao) {
            $valor = preg_replace_callback(
                '/\{([^}]+)\}/',
                fn (array $m) => (string) ($contexto->dados[$m[1]] ?? ''),
                (string) $condicao->valor,
            );

            $condicao->operador === 'like'
                ? $consulta->where($condicao->campo, 'ilike', "%{$valor}%")
                : $consulta->where($condicao->campo, $condicao->operador, $valor);
        }

        return $consulta;
    }
}
