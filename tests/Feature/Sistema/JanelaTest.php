<?php

use App\Filament\Resources\Dicionario\ModuloResource\Pages\CreateModulo;
use App\Filament\Resources\Dicionario\ModuloResource\Pages\EditModulo;
use App\Filament\Resources\Dicionario\ModuloResource\Pages\ListModulos;
use App\Models\User;
use App\Sistema\Runtime\Janela;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Livewire\Livewire;

/**
 * Janelas (iframe com X e −): o modo vem da URL, ou do Referer da própria página nas atualizações do
 * Livewire. A parte visual (Alpine) não roda aqui; o que se garante é o contrato do servidor.
 */
it('o modo janela vem dos parâmetros da URL', function () {
    request()->query->replace(['janela' => '1', 'modo' => 'selecionar', 'campo' => 'data.CLIENTE_ID', 'recurso' => 'op_cliente', 'filtro_id' => '42']);

    expect(Janela::ativa())->toBeTrue()
        ->and(Janela::modo())->toBe('selecionar')
        ->and(Janela::campo())->toBe('data.CLIENTE_ID')
        ->and(Janela::recurso())->toBe('op_cliente')
        ->and(Janela::filtroId())->toBe('42');
});

it('sem parâmetros não é janela', function () {
    expect(Janela::ativa())->toBeFalse()
        ->and(Janela::modo())->toBeNull()
        ->and(Janela::filtroId())->toBeNull();
});

it('nas atualizações do Livewire o modo vem do Referer da mesma página', function () {
    request()->headers->set('referer', 'http://'.request()->getHost().'/admin/cliente?janela=1&filtro_id=7&recurso=op_cliente');

    expect(Janela::ativa())->toBeTrue()
        ->and(Janela::filtroId())->toBe('7')
        ->and(Janela::recurso())->toBe('op_cliente');
});

it('ignora o Referer de outro site', function () {
    request()->headers->set('referer', 'http://outro-site.test/x?janela=1&filtro_id=7');

    expect(Janela::ativa())->toBeFalse()
        ->and(Janela::filtroId())->toBeNull();
});

it('monta a URL da janela sem parâmetros vazios', function () {
    expect(Janela::url('http://x.test/admin/cliente', ['modo' => 'selecionar', 'campo' => 'data.A', 'filtro_id' => null]))
        ->toBe('http://x.test/admin/cliente?janela=1&modo=selecionar&campo=data.A')
        ->and(Janela::url('http://x.test/a?b=1', ['filtro_id' => 3]))->toBe('http://x.test/a?b=1&janela=1&filtro_id=3');
});

it('os scripts escapam o conteúdo, sem deixar fechar a tag nem a aspa', function () {
    $abrir = Janela::scriptAbrir('Buscar </script><b>"x"', 'http://x.test/a?b=1&c=2');
    expect($abrir)->not->toContain('</script>')->not->toContain('<b>')
        ->and($abrir)->toContain('window.Janelas');
});

it('a barra guarda no máximo 5 janelas', function () {
    expect(Janela::LIMITE)->toBe(5);

    $html = view('components.janelas-gerenciador')->render();

    expect($html)->toContain('janelasGerenciador')
        ->toContain('limite: 5')
        ->toContain('Diminuir')
        ->toContain('.fi-sidebar a[href]')
        ->toContain('Fechar');
});

it('dentro de uma janela não há gerenciador, e o menu some', function () {
    request()->query->replace(['janela' => '1']);

    expect(trim(view('components.janelas-gerenciador')->render()))->not->toContain('janelasGerenciador')
        ->and(view('components.janela-estilos')->render())->toContain('.fi-sidebar')->toContain("classList.add('jn-iframe')");
});

it('o iframe se reconhece sozinho, sem depender do parâmetro da URL', function () {
    $html = view('components.janela-estilos')->render();

    expect($html)->toContain('window.self !== window.top')->toContain('html.jn-iframe .fi-sidebar');
});

it('as notificações do painel aparecem num menu suspenso, não no drawer lateral', function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    Livewire::test(DatabaseNotifications::class)
        ->assertSee('fi-no-database')
        ->assertDontSee('slide-over');
});

it('os títulos das telas seguem o padrão: gerenciamento, manutenção e visualização', function () {
    $lista = new class extends ListModulos {};
    $criar = new class extends CreateModulo {};
    $editar = new class extends EditModulo {};

    expect((string) $lista->getTitle())->toBe('Gerenciamento de Módulos')
        ->and((string) $criar->getTitle())->toBe('Manutenção de Módulo')
        ->and((string) $editar->getTitle())->toBe('Manutenção de Módulo');
});

it('a barra de baixo mostra o contador de janelas', function () {
    expect(view('components.janelas-gerenciador')->render())->toContain('lista.length')->toContain('badge');
});

it('a barra tem fechar todas e a dica do limite, e pergunta antes de fechar formulário', function () {
    expect(view('components.janelas-gerenciador')->render())
        ->toContain('Fechar todas as janelas')
        ->toContain('Limite de')
        ->toContain('sem salvar os dados')->toContain('x-tooltip')->toContain('jn-confirmar')->not->toContain('window.confirm');
});

it('o menu lateral tem a seta de recolher dentro dele, e recolhido não mostra mais nada', function () {
    expect(view('components.sidebar-recolher')->render())->toContain('Recolher o menu')->toContain('jn-recolher')->toContain(':not(.jn-recolher)');
});

it('o menu lateral tem a pesquisa de módulos e telas só com a lupa', function () {
    expect(view('components.menu-pesquisa')->render())->toContain('menu-pesquisa')->toContain('fi-sidebar-group');
});
