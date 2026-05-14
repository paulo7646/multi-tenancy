<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ImpersonateController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        $userId = Cache::pull("impersonate:{$token}");

        abort_unless($userId !== null, 403);

        Auth::loginUsingId($userId);

        return redirect('/client');
    }
}
