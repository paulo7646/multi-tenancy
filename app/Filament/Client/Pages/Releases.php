<?php

namespace App\Filament\Client\Pages;

use App\Livewire\Sistema\ReleasesDaEmpresa;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

/**
 * Releases que a base pai publicou para os módulos desta empresa, com o botão que aplica as pendentes.
 */
class Releases extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Releases';

    protected static ?string $title = 'Releases';

    protected static ?string $slug = 'releases';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return tenancy()->initialized && (bool) auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Livewire::make(ReleasesDaEmpresa::class)->key('releases-da-empresa'),
        ]);
    }
}
