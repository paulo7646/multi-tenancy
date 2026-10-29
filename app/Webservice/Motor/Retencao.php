<?php

namespace App\Webservice\Motor;

use App\Webservice\Models\Integracao;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Apaga integrações antigas (e o arquivo de cada uma) no banco atual.
 */
class Retencao
{
    /**
     * @return int quantas integrações foram apagadas
     */
    public static function limpar(?int $dias = null): int
    {
        if (! Schema::hasTable('ws_integracoes')) {
            return 0;
        }

        $limite = now()->subDays($dias ?? (int) config('webservice.retencao_dias', 90));
        $apagadas = 0;

        Integracao::whereNotNull('finalizada_em')->where('finalizada_em', '<', $limite)
            ->chunkById(200, function ($integracoes) use (&$apagadas) {
                foreach ($integracoes as $integracao) {
                    if ($integracao->arquivo) {
                        Storage::disk('local')->delete($integracao->arquivo);
                    }

                    $integracao->delete();
                    $apagadas++;
                }
            });

        return $apagadas;
    }
}
