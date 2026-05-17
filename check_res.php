<?php

use Filament\Facades\Filament;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['admin', 'client'] as $panelId) {
    try {
        $resources = Filament::getPanel($panelId)->getResources();
        echo "Resources registrados no painel '$panelId':\n";
        foreach ($resources as $resource) {
            echo "- $resource\n";
        }
    } catch (\Exception $e) {
        echo "Erro ao carregar painel '$panelId': " . $e->getMessage() . "\n";
    }
    echo "\n";
}
