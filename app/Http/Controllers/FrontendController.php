<?php

namespace App\Http\Controllers;

use App\Services\ClienteFrontend;
use Illuminate\Contracts\View\View;

class FrontendController extends Controller
{
    /**
     * "/" do domínio central -> views/multitenant
     */
    public function central(): View
    {
        return view('spa', [
            'entry' => 'views/multitenant/main.js',
            'props' => [
                'appName' => config('app.name'),
                'adminUrl' => url('/admin'),
            ],
        ]);
    }

    /**
     * "/" do domínio da empresa -> views/clientes/{slug}
     */
    public function tenant(): View
    {
        $tenant = tenant();

        abort_if(blank($tenant->slug) || ! ClienteFrontend::exists($tenant->slug), 404);

        return view('spa', [
            'entry' => ClienteFrontend::entry($tenant->slug),
            'title' => $tenant->slug,
            'props' => [
                'tenant' => ['slug' => $tenant->slug],
                'adminUrl' => url('/admin'),
            ],
        ]);
    }
}
