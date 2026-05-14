<?php

namespace App\Livewire;

use App\Models\UserLicense;
use Livewire\Attributes\On;
use Livewire\Component;

class LicenseMonitor extends Component
{
    public bool $showBanner = false;
    public ?int $warningAt = null;
    public int $minutesRemaining = 30;

    public function mount(): void
    {
        $this->warningAt = session('license_warning_at');
        $this->updateBannerState();
    }

    public function checkLicense(): void
    {
        $user = auth()->user();

        if (!$user || !function_exists('tenant') || !tenant()) {
            return;
        }

        $hasActiveLicense = UserLicense::findActive($user->email, tenant()->getTenantKey()) !== null;

        if (!$hasActiveLicense) {
            if (!session()->has('license_warning_at')) {
                session(['license_warning_at' => now()->timestamp]);
            }
            $this->warningAt = session('license_warning_at');
        } else {
            session()->forget('license_warning_at');
            $this->warningAt = null;
        }

        $this->updateBannerState();

        if ($this->showBanner && $this->minutesRemaining <= 0) {
            $this->forceLogout();
        }
    }

    private function updateBannerState(): void
    {
        if (!$this->warningAt) {
            $this->showBanner = false;
            $this->minutesRemaining = 30;
            return;
        }

        $elapsed = now()->timestamp - $this->warningAt;
        $this->minutesRemaining = max(0, 30 - (int) ($elapsed / 60));
        $this->showBanner = true;

        if ($this->minutesRemaining <= 0) {
            $this->forceLogout();
        }
    }

    private function forceLogout(): void
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('license.invalid'));
    }

    public function render()
    {
        return view('livewire.license-monitor');
    }
}
