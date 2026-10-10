<?php

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Filament\Resources\TenantResource;
use App\Filament\Resources\UserLicenseResource;
use App\Models\User;
use App\Models\UserLicense;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LicenseRequestController extends Controller
{
    private const SESSION_KEY = 'license_request_user';

    /**
     * Guarda na sessão quem foi deslogado por falta de licença, para que a
     * tela de licença saiba de quem é a solicitação. Chamar depois de
     * invalidar a sessão.
     */
    public static function rememberUser(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'name' => $user->name,
            'email' => $user->email,
            'filial' => $user->filial?->nome,
        ]);
    }

    public function show(Request $request)
    {
        $requester = $request->session()->get(self::SESSION_KEY);

        // Licença liberada enquanto o usuário esperava: ao recarregar a
        // tela ele já entra direto, sem precisar logar de novo.
        if ($requester && UserLicense::findActive($requester['email'], tenant()->getTenantKey())) {
            $user = User::where('email', $requester['email'])->first();

            if ($user) {
                Auth::login($user);
                $request->session()->forget(self::SESSION_KEY);
                $request->session()->regenerate();

                return redirect('/admin');
            }
        }

        return view('license-invalid', [
            'requester' => $requester,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $requester = $request->session()->get(self::SESSION_KEY);

        if (! $requester) {
            return back()->with('license_request', 'expired');
        }

        $tenant = tenant();

        // Já existe licença (ativa ou não) para este usuário nesta empresa:
        // não cadastra outra e não notifica.
        $exists = UserLicense::where('user_email', $requester['email'])
            ->where('tenant_id', $tenant->getTenantKey())
            ->exists();

        if ($exists) {
            return back()->with('license_request', 'exists');
        }

        // Mesmo acima do limite a solicitação é cadastrada; o admin decide
        // se aumenta a quantidade de licenças da empresa para ativá-la.
        $limitReached = $tenant->licenseLimitReached();

        $license = UserLicense::create([
            'user_email' => $requester['email'],
            'tenant_id' => $tenant->getTenantKey(),
            'license_key' => (string) Str::uuid(),
            'status' => LicenseStatus::Requested,
        ]);

        tenancy()->central(function () use ($license, $requester, $tenant, $limitReached) {
            $body = "{$requester['name']} ({$requester['email']}) da empresa {$tenant->displayName()}";

            if ($requester['filial']) {
                $body .= ", filial {$requester['filial']}";
            }

            $body .= '.';

            if ($limitReached) {
                $body .= " A empresa excedeu o limite de licenças ({$tenant->activeLicensesCount()}/{$tenant->qtd_licencas} ativas). "
                    . 'Aumente a quantidade de licenças da empresa para poder ativar esta.';
            }

            $actions = [
                Action::make('ver')
                    ->label('Ver licença')
                    ->url(UserLicenseResource::getUrl('edit', ['record' => $license], panel: 'admin'))
                    ->markAsRead(),
            ];

            if ($limitReached) {
                $actions[] = Action::make('empresa')
                    ->label('Ajustar limite da empresa')
                    ->color('danger')
                    ->url(TenantResource::getUrl('edit', ['record' => $tenant], panel: 'admin'))
                    ->markAsRead();
            }

            $notification = Notification::make()
                ->title($limitReached ? 'Solicitação de licença: limite excedido' : 'Solicitação de licença')
                ->body($body)
                ->icon($limitReached ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-key')
                ->status($limitReached ? 'danger' : 'info')
                ->actions($actions);

            // notifyNow: grava direto, sem depender do worker da fila
            foreach (User::all() as $admin) {
                $admin->notifyNow($notification->toDatabase());
            }
        });

        return back()->with('license_request', $limitReached ? 'limit' : 'created');
    }
}
