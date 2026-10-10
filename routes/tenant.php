<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ImpersonateController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\LicenseRequestController;
use App\Http\Middleware\CheckTenantStatus;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Página da empresa em Vue: views/clientes/{slug}
    Route::get('/', [FrontendController::class, 'tenant'])
        ->middleware(CheckTenantStatus::class);

    Route::get('/license-invalid', [LicenseRequestController::class, 'show'])
        ->name('license.invalid');

    Route::post('/license-request', [LicenseRequestController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('license.request');

    Route::get('/tenant-disabled', fn() => view('tenant-disabled'))
        ->name('tenant.disabled');

    Route::get('/impersonate', ImpersonateController::class)
        ->name('tenant.impersonate');
});
