<?php

namespace App\Livewire;

use App\Models\Filial;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class FilialSelectorTopbar extends Component
{
    public ?int $filial_id = null;

    public static function shouldRender(): bool
    {
        return Auth::check() && ! Auth::user()->hasFilial();
    }

    public function mount(): void
    {
        $this->filial_id = Session::get('filial_ativa');
    }

    public function getFiliais(): array
    {
        return Filial::where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function updatedFilialId(?int $value): void
    {
        Session::put('filial_ativa', $value);
        $this->dispatch('filial-changed');
        $this->redirect(request()->header('Referer') ?? '/client');
    }

    public function render()
    {
        return view('livewire.filial-selector-topbar');
    }
}
