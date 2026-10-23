<?php

namespace App\Filament\Sistema\Pages;

use App\Filament\Resources\Dicionario\MdGerenciamentoResource;
use App\Filament\Resources\Dicionario\MdTabelaResource;
use App\Models\Md\MdGerenciamento;
use App\Models\Md\Modulo;
use App\Sistema\Registro;
use App\Sistema\Runtime\Acesso;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * Visão geral de um módulo: o primeiro item do grupo dele no menu, com os
 * gerenciamentos em cards. Existe desde a criação do módulo.
 */
class ModuloPage extends Page
{
    protected static ?string $slug = 'm/modulo/{modulo}';

    protected string $view = 'filament.sistema.modulo';

    protected static bool $shouldRegisterNavigation = false;

    public string $modulo;

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'sistema.modulo';
    }

    public function mount(string $modulo): void
    {
        $this->modulo = $modulo;

        abort_unless(Acesso::moduloNoPainel($this->md()), 403);
    }

    public function md(): Modulo
    {
        return Modulo::where('prefixo', $this->modulo)->firstOrFail();
    }

    public function getTitle(): string | Htmlable
    {
        return $this->md()->nome;
    }

    public function getSubheading(): ?string
    {
        return $this->md()->descricao;
    }

    /**
     * @return Collection<int, MdGerenciamento>
     */
    public function gerenciamentos(): Collection
    {
        return MdGerenciamento::query()
            ->whereHas('tabela', fn ($q) => $q->where('modulo_id', $this->md()->id))
            ->with('tabela')
            ->orderBy('ordem')
            ->orderBy('titulo')
            ->get()
            // só as telas que o usuário pode abrir (permissão do Shield)
            ->filter(fn (MdGerenciamento $gerenciamento) => $this->urlGerenciamento($gerenciamento))
            ->values();
    }

    public function urlGerenciamento(MdGerenciamento $gerenciamento): ?string
    {
        $resource = Registro::resource($gerenciamento->loadMissing('tabela.modulo'));

        return $resource && $resource::canAccess() ? $resource::getUrl('index') : null;
    }

    /**
     * Atalho para o dicionário (só onde ele está disponível: painel central, local).
     */
    public function urlDicionario(): ?string
    {
        if (! MdTabelaResource::canAccess() || filament()->getCurrentPanel()?->getId() !== 'admin') {
            return null;
        }

        return MdGerenciamentoResource::getUrl('index');
    }
}
