<?php

use App\Http\Controllers\FrontendController;
use App\Plataforma\Http\Rotas as Rota;
use App\Webservice\Http\EntradaController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// Sistema principal em Vue: views/multitenant
Route::get('/', [FrontendController::class, 'central'])->name('home');

// webservices disponibilizados: terceiros enviam JSON/XML para /ws/{webservice}/{rota}
Route::any('/ws/{webservice}/{rota?}', EntradaController::class)
    ->where('rota', '.*')
    ->middleware('throttle:webservice')
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('webservice.entrada');

// assinatura eletrônica: link do assinante e verificação de certificado
Rota::publicas();

// login, perfil e senha ficam nos painéis do Filament (/admin)
