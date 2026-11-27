<?php

use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

// Sistema principal em Vue: views/multitenant
Route::get('/', [FrontendController::class, 'central'])->name('home');

// login, perfil e senha ficam nos painéis do Filament (/admin)
