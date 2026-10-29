<?php

return [
    /*
    | Executar integrações e atualizar clientes pela fila (precisa de
    | php artisan queue:work rodando). Desligado: roda na hora, dentro da requisição.
    */
    'fila' => env('WEBSERVICE_FILA', false),

    // Integrações finalizadas há mais dias que isto são apagadas (com o arquivo) por webservice:limpar
    'retencao_dias' => env('WEBSERVICE_RETENCAO_DIAS', 90),

    // Chamadas por minuto, por IP e webservice, na entrada /ws
    'limite_por_minuto' => env('WEBSERVICE_LIMITE_POR_MINUTO', 120),
];
