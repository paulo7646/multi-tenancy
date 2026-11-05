<?php

namespace App\Plataforma\Impressao;

use App\Models\Md\MdTabela;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Exporta os registros de uma tabela para Excel (xlsx) ou CSV, em blocos
 * (não carrega a tabela inteira na memória).
 */
class Planilhas
{
    /**
     * Grava num arquivo temporário e devolve o caminho. Quem chama apaga depois.
     *
     * @param  Builder<Model>  $consulta
     */
    public static function gerar(MdTabela $tabela, Builder $consulta, string $formato): string
    {
        $tabela->loadMissing(['situacoes.status']);
        $colunas = Valores::colunas($tabela);
        $caminho = tempnam(sys_get_temp_dir(), 'exp').($formato === 'csv' ? '.csv' : '.xlsx');

        $escritor = $formato === 'csv' ? new CsvWriter : new XlsxWriter;
        $escritor->openToFile($caminho);
        $escritor->addRow(Row::fromValues(array_column($colunas, 0)));

        $consulta->chunk(500, function ($registros) use ($escritor, $colunas, $tabela) {
            foreach ($registros as $registro) {
                $escritor->addRow(Row::fromValues(array_map(
                    fn (array $c) => static::seguro(Valores::de($registro, $c[1], $tabela)),
                    $colunas,
                )));
            }
        });

        $escritor->close();

        return $caminho;
    }

    /**
     * Texto que começa com = + - @ vira fórmula no Excel: prefixa com apóstrofo (injeção de fórmula).
     */
    public static function seguro(string $valor): string
    {
        return $valor !== '' && str_contains('=+-@', $valor[0]) && ! is_numeric($valor) ? "'".$valor : $valor;
    }
}
