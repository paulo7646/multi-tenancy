<?php

namespace App\Webservice;

use App\Models\Md\MdTabela;
use App\Sistema\Enums\Banco;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Onde o webservice está rodando (base pai ou cliente) e o que o dicionário
 * oferece nesse banco: tabelas, campos e funções dos controllers.
 */
class Contexto
{
    public static function banco(): Banco
    {
        return function_exists('tenancy') && tenancy()->initialized ? Banco::Tenant : Banco::Central;
    }

    /**
     * @return array<string, string> nome_fisico => nome
     */
    public static function tabelas(): array
    {
        return MdTabela::where('banco', static::banco()->value)
            ->orderBy('nome_fisico')
            ->get()
            ->filter(fn (MdTabela $t) => $t->tipoTabela()->editavel())
            ->mapWithKeys(fn (MdTabela $t) => [$t->nome_fisico => "{$t->nome} ({$t->nome_fisico})"])
            ->all();
    }

    /**
     * @return array<string, string> nome_fisico => nome
     */
    public static function campos(?string $tabela): array
    {
        $md = static::tabela($tabela);

        if (! $md) {
            return [];
        }

        return $md->campos()->orderBy('ordem')->get()
            ->mapWithKeys(fn ($c) => [$c->nome_fisico => "{$c->nome} ({$c->nome_fisico})"])
            ->prepend('ID (id)', 'id')
            ->all();
    }

    /**
     * Funções públicas escritas no controller da tabela (as herdadas do sistema ficam de fora).
     *
     * @return array<string, string> nome => nome
     */
    public static function funcoes(?string $tabela): array
    {
        $md = static::tabela($tabela);

        if (! $md) {
            return [];
        }

        try {
            $classe = $md->controllerFqcn();

            if (! class_exists($classe)) {
                return [];
            }

            return collect((new ReflectionClass($classe))->getMethods(ReflectionMethod::IS_PUBLIC))
                ->filter(fn (ReflectionMethod $m) => $m->class === $classe && ! $m->isConstructor() && ! str_starts_with($m->name, '__'))
                ->mapWithKeys(fn (ReflectionMethod $m) => [$m->name => $m->name])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected static function tabela(?string $nomeFisico): ?MdTabela
    {
        return filled($nomeFisico)
            ? MdTabela::where('banco', static::banco()->value)->where('nome_fisico', $nomeFisico)->first()
            : null;
    }
}
