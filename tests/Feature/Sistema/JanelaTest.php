<?php

use App\Sistema\Runtime\Janela;

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
    $selecionar = Janela::scriptSelecionar("data.A'];alert(1)//", 5);

    expect($abrir)->not->toContain('</script>')->not->toContain('<b>')
        ->and($abrir)->toContain('window.Janelas')
        ->and($selecionar)->not->toContain("'];alert(1)//")
        ->and($selecionar)->toContain('window.parent.postMessage')->toContain('"id":5');
});

it('a barra guarda no máximo 5 janelas', function () {
    expect(Janela::LIMITE)->toBe(5);

    $html = view('components.janelas-gerenciador')->render();

    expect($html)->toContain('janelasGerenciador')
        ->toContain('limite: 5')
        ->toContain('Diminuir')
        ->toContain('Fechar');
});

it('dentro de uma janela não há gerenciador, e o menu some', function () {
    request()->query->replace(['janela' => '1']);

    expect(trim(view('components.janelas-gerenciador')->render()))->not->toContain('janelasGerenciador')
        ->and(view('components.janela-estilos')->render())->toContain('.fi-sidebar');
});

it('fora de uma janela os estilos de janela não saem', function () {
    expect(trim(view('components.janela-estilos')->render()))->toBe('');
});
