<?php

namespace App\Sistema\Geradores;

use App\Models\Md\MdGerenciamento;
use App\Sistema\Base\BaseResource;

/**
 * Tela padrão de um gerenciamento sem arquivos gerados: o Resource e as páginas (Listar, Criar, Alterar,
 * Visualizar) são criados em memória, com os mesmos nomes que o gerador usaria, e tudo vem do dicionário.
 * Só vale a pena gerar os arquivos ("Criar arquivos") para sobrescrever algum método de BaseResource;
 * com o arquivo no disco, ele é carregado no lugar deste.
 */
class ResourceVirtual
{
    /**
     * Garante que a classe do Resource (e as páginas) exista, em disco ou em memória.
     *
     * @return class-string<BaseResource>|null null se os nomes do dicionário não servem de nome de classe
     */
    public static function garantir(MdGerenciamento $gerenciamento): ?string
    {
        $classe = $gerenciamento->resourceFqcn();

        // arquivo gerado (ou já definido em memória)
        if (class_exists($classe)) {
            return $classe;
        }

        // tabela feita à mão (incorporada): a tela de verdade é a escrita à mão, o dicionário não cria outra
        if ($gerenciamento->tabela->existente) {
            return null;
        }

        $namespace = $gerenciamento->tabela->namespace();
        $nome = $gerenciamento->classeResource();

        // os nomes vêm do dicionário e entram em código: só identificadores válidos
        foreach ([...explode('\\', $namespace), $nome] as $parte) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $parte)) {
                return null;
            }
        }

        $bs = '\\';
        $base = $bs.'App'.$bs.'Sistema'.$bs.'Base'.$bs;
        $nomeFisico = var_export($gerenciamento->nome_fisico, true);

        eval(sprintf(
            'namespace %s; class %s extends %sBaseResource { protected static string $gerenciamento = %s; }',
            $namespace, $nome, $base, $nomeFisico,
        ));

        foreach (ClassesWriter::PAGINAS as $pagina => $paginaBase) {
            eval(sprintf(
                'namespace %s%s%s%sPages; class %s extends %sPages%s%s { protected static string $resource = %s%s%s%s::class; }',
                $namespace, $bs, $nome, $bs, $pagina, $base, $bs, $paginaBase, $bs, $namespace, $bs, $nome,
            ));
        }

        return $classe;
    }
}
