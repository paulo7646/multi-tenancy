<?php

namespace App\Plataforma\Http;

use Illuminate\Support\Facades\Route;

/**
 * Rotas públicas da plataforma, registradas no domínio central (routes/web.php)
 * e nos domínios dos clientes (routes/tenant.php).
 */
class Rotas
{
    public static function publicas(): void
    {
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('/assinar/{token}', [AssinaturaPublicaController::class, 'mostrar']);
            Route::get('/assinar/{token}/documento', [AssinaturaPublicaController::class, 'documento']);
            Route::post('/assinar/{token}/codigo', [AssinaturaPublicaController::class, 'enviarCodigo'])->middleware('throttle:10,1');
            Route::post('/assinar/{token}', [AssinaturaPublicaController::class, 'assinar'])->middleware('throttle:20,1');
            Route::post('/assinar/{token}/recusar', [AssinaturaPublicaController::class, 'recusar'])->middleware('throttle:10,1');
            Route::get('/verificar/{codigo?}', [AssinaturaPublicaController::class, 'verificar']);
        });
    }
}
