<?php

namespace App\Http\Controllers;

use App\Services\ClienteFrontend;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
    public function tenant(): View|RedirectResponse
    {
        $tenant = tenant();

        // empresa sem página personalizada (sem pasta) abre direto o painel
        if (blank($tenant->slug) || ! ClienteFrontend::exists($tenant->slug)) {
            return redirect('/admin');
        }

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
