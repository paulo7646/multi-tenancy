<?php

namespace App\Sistema\Schema;

use Illuminate\Support\Facades\DB;

/**
 * Grava os itens de uma tabela lista/conteúdo (chamado pelas migrations
 * geradas): inclui os novos, atualiza a descrição dos que já existem e tira
 * os que saíram da lista. Os registros ficam iguais em todos os bancos.
 */
class ItensDaTabela
{
    /**
     * @param  string  $chave  CODIGO (lista) ou VALOR (conteúdo)
     * @param  list<array<string, mixed>>  $itens  [[CODIGO|VALOR => ..., DESCRICAO => ...]]
     */
    public static function sincronizar(string $tabela, string $chave, array $itens): void
    {
        $agora = now();
        $chaves = [];

        foreach ($itens as $item) {
            $chaves[] = $item[$chave];
            $existe = DB::table($tabela)->where($chave, $item[$chave])->exists();

            $existe
                ? DB::table($tabela)->where($chave, $item[$chave])->update([...$item, 'updated_at' => $agora])
                : DB::table($tabela)->insert([...$item, 'created_at' => $agora, 'updated_at' => $agora]);
        }

        // itens que saíram da lista (se algum registro apontar para eles, a FK impede e a migration falha)
        DB::table($tabela)->whereNotIn($chave, $chaves)->delete();
    }
}
