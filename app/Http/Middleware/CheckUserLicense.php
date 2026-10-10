<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LicenseRequestController;
use App\Models\UserLicense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !function_exists('tenant') || !tenant()) {
            return $next($request);
        }

        $license = UserLicense::findActive($user->email, tenant()->getTenantKey());

        if (!$license) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            LicenseRequestController::rememberUser($request, $user);

            return redirect()->route('license.invalid');
        }

        return $next($request);
    }
}
