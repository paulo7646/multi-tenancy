{{--
    Tela aberta dentro de uma janela (iframe): sem menu lateral nem barra do topo, só o conteúdo.
    O navegador reconhece sozinho que está num iframe (o parâmetro ?janela=1 se perde ao navegar dentro da janela,
    por exemplo ao clicar no "Empresas" do caminho de volta).
--}}
<style>
    html.jn-iframe .fi-sidebar, html.jn-iframe .fi-sidebar-close-overlay, html.jn-iframe .fi-topbar, html.jn-iframe .fi-topbar-ctn { display: none !important; }
    html.jn-iframe .fi-main-ctn { margin-inline-start: 0 !important; }
    html.jn-iframe .fi-main { max-width: none !important; }
</style>

<script>
    // dentro do iframe de uma janela: marca a página e acompanha o tema da página principal
    if (window.self !== window.top) {
        document.documentElement.classList.add('jn-iframe');

        // formulário de inclusão/alteração com dados digitados e ainda não salvos (a página principal lê isto antes de fechar a janela)
        window.jnAlterado = false;
        ['input', 'change'].forEach((tipo) => document.addEventListener(tipo, (evento) => {
            if (evento.isTrusted && evento.target.closest?.('form[wire\\:submit]')) {
                window.jnAlterado = true;
            }
        }, true));

        document.addEventListener('livewire:init', () => {
            // depois de salvar com sucesso, não há mais o que perder
            window.Livewire?.hook('commit', ({ commit, succeed }) => {
                const salvou = (commit?.calls ?? []).some((chamada) => ['save', 'create'].includes(chamada.method));

                succeed(({ effects }) => {
                    if (salvou && ! (effects?.errors && Object.keys(effects.errors).length)) {
                        window.jnAlterado = false;
                    }
                });
            });
        });

        window.addEventListener('message', (evento) => {
            if (evento.origin === window.location.origin && evento.source === window.parent && evento.data?.tipo === 'tema') {
                document.documentElement.classList.toggle('dark', !! evento.data.escuro);
            }
        });
    }
</script>

@if (\App\Sistema\Runtime\Janela::modo() === 'selecionar' && filled($campo = \App\Sistema\Runtime\Janela::campo()))
    <script>
        // lista aberta pela lupa de um campo de FK: o duplo clique na linha devolve o registro ao campo
        document.addEventListener('dblclick', (evento) => {
            const chave = evento.target.closest?.('.fi-ta-row')?.getAttribute('wire:key')?.match(/\.table\.records\.(.+)$/)?.[1];

            if (chave !== undefined && window.parent !== window) {
                window.parent.postMessage({ tipo: 'fk-selecionado', campo: @js($campo), id: chave }, window.location.origin);
            }
        });
    </script>
@endif

@if (\App\Sistema\Runtime\Janela::ativa())
    <script>
        // aberta direto com ?janela=1 (fora de um iframe): também sem o menu
        document.documentElement.classList.add('jn-iframe');
    </script>
@endif
