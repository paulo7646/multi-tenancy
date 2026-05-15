<?php

namespace App\Providers;

use App\Models\UserLicense;
use App\Observers\UserLicenseObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        UserLicense::observe(UserLicenseObserver::class);
    }
}
