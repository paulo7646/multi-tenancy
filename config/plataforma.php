<?php

return [
    'documentos' => [
        // disco do config/filesystems.php; troque por s3 no .env para guardar na nuvem
        'disco' => env('DOCUMENTOS_DISCO', 'local'),

        // tamanho máximo de um arquivo, em MB
        'max_mb' => (int) env('DOCUMENTOS_MAX_MB', 50),

        // cota de armazenamento por empresa (e da base pai), em MB; vazio = sem limite
        'cota_mb' => env('DOCUMENTOS_COTA_MB') !== null ? (int) env('DOCUMENTOS_COTA_MB') : null,

        // extensões que não podem ser enviadas
        'extensoes_bloqueadas' => ['exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'js', 'vbs', 'ps1', 'sh', 'php', 'phtml', 'jar', 'dll'],
    ],

    'assinatura' => [
        // validade do link de assinatura, em dias
        'validade_dias' => (int) env('ASSINATURA_VALIDADE_DIAS', 7),

        // validade do código enviado por e-mail, em minutos, e tentativas erradas permitidas
        'codigo_minutos' => 15,
        'codigo_tentativas' => 5,
    ],
];
