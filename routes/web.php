<?php

use App\Http\Controllers\FrontendController;
use App\Webservice\Http\EntradaController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// Sistema principal em Vue: views/multitenant
Route::get('/', [FrontendController::class, 'central'])->name('home');

// webservices disponibilizados: terceiros enviam JSON/XML para /ws/{webservice}/{rota}
Route::any('/ws/{webservice}/{rota?}', EntradaController::class)
    ->where('rota', '.*')
    ->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class])
    ->name('webservice.entrada');

// login, perfil e senha ficam nos painéis do Filament (/admin)
