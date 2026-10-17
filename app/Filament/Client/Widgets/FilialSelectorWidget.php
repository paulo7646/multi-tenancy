<?php

namespace App\Filament\Client\Widgets;

use Filament\Actions\Concerns\InteractsWithActions;
use App\Models\Filial;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class FilialSelectorWidget extends Widget implements HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.client.widgets.filial-selector-widget';

    protected static ?int $sort = -10;

    public ?int $filial_id = null;

    public static function canView(): bool
    {
        return false;
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
        $this->redirect(request()->header('Referer') ?? '/admin');
    }
}
