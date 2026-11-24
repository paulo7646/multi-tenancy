{{--
    Gerenciador de janelas: telas do sistema abertas em iframe, com X (fecha) e − (minimiza para a barra
    inferior). A janela minimizada só some da tela: o iframe continua vivo, com o que foi digitado.
    Máximo de {{ \App\Sistema\Runtime\Janela::LIMITE }} janelas; a mais antiga fecha quando entra a seguinte.
    Dentro de uma janela (?janela=1) não há gerenciador: ele fica só na página principal.
--}}
@unless (\App\Sistema\Runtime\Janela::ativa())
    <style>
        .jn-raiz { --jn-fundo: light-dark(#ffffff, #18181b); --jn-borda: light-dark(#e4e4e7, #3f3f46); --jn-texto: light-dark(#18181b, #fafafa); --jn-suave: light-dark(#f4f4f5, #27272a); }
        .jn-janela { position: fixed; inset: 0 0 56px 0; margin: auto; width: min(56rem, 94vw); height: min(38rem, calc(100vh - 7rem)); z-index: 60; display: flex; flex-direction: column; background: var(--jn-fundo); color: var(--jn-texto); border: 1px solid var(--jn-borda); border-radius: .75rem; box-shadow: 0 25px 50px -12px rgb(0 0 0 / .45); overflow: hidden; }
        .jn-topo { display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; background: var(--jn-suave); border-bottom: 1px solid var(--jn-borda); }
        .jn-titulo { flex: 1; font-weight: 600; font-size: .875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .jn-botao { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: .375rem; border: 0; background: transparent; color: inherit; cursor: pointer; font-size: 1.125rem; line-height: 1; }
        .jn-botao:hover { background: var(--jn-borda); }
        .jn-frame { flex: 1; width: 100%; border: 0; background: var(--jn-fundo); }
        .jn-barra { position: fixed; left: 0; right: 0; bottom: 0; z-index: 70; display: flex; gap: .5rem; padding: .5rem .75rem; background: var(--jn-suave); border-top: 1px solid var(--jn-borda); overflow-x: auto; }
        .jn-item { display: inline-flex; align-items: center; gap: .25rem; max-width: 16rem; padding: .25rem .25rem .25rem .75rem; border: 1px solid var(--jn-borda); border-radius: .5rem; background: var(--jn-fundo); color: var(--jn-texto); font-size: .8125rem; }
        .jn-item[data-ativa='true'] { outline: 2px solid #f59e0b; }
        .jn-item-titulo { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; background: none; border: 0; color: inherit; padding: 0; }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('janelasGerenciador', () => ({
                lista: [],
                limite: {{ \App\Sistema\Runtime\Janela::LIMITE }},

                init() {
                    window.Janelas = {
                        abrir: (dados) => this.abrir(dados),
                        fechar: (id) => this.fechar(id),
                        lista: () => this.lista,
                    };

                    // a tela dentro da janela devolve o registro escolhido (lupa do campo de FK)
                    window.addEventListener('message', (evento) => {
                        if (evento.origin !== window.location.origin || evento.data?.tipo !== 'fk-selecionado') {
                            return;
                        }

                        // só vale a escolha vinda de uma das janelas abertas aqui
                        const janela = this.lista.find((j) => this.quadro(j.id)?.contentWindow === evento.source);

                        if (! janela) {
                            return;
                        }

                        window.dispatchEvent(new CustomEvent('fk-selecionado', { detail: { campo: evento.data.campo, id: evento.data.id } }));
                        this.fechar(janela.id);
                    });
                },

                quadro(id) {
                    return this.$root.querySelector(`iframe[data-janela="${CSS.escape(id)}"]`);
                },

                abrir({ titulo, url, id }) {
                    // só telas do próprio sistema (mesma origem, http/https): nada de javascript: nem site de fora
                    try {
                        const destino = new URL(url, window.location.href);

                        if (destino.origin !== window.location.origin || ! ['http:', 'https:'].includes(destino.protocol)) {
                            return;
                        }

                        url = destino.href;
                    } catch (erro) {
                        return;
                    }

                    id = id || url;

                    if (this.lista.some((j) => j.id === id)) {
                        return this.restaurar(id);
                    }

                    this.lista.forEach((j) => (j.minimizada = true));
                    this.lista.push({ id, titulo: titulo || 'Janela', url, minimizada: false });

                    // passou do limite: a mais antiga sai
                    while (this.lista.length > this.limite) {
                        this.lista.shift();
                    }
                },

                restaurar(id) {
                    this.lista.forEach((j) => (j.minimizada = j.id !== id));
                },

                minimizar(id) {
                    const janela = this.lista.find((j) => j.id === id);

                    if (janela) {
                        janela.minimizada = true;
                    }
                },

                fechar(id) {
                    this.lista = this.lista.filter((j) => j.id !== id);
                },
            }));
        });
    </script>

    <div class="jn-raiz" x-data="janelasGerenciador" x-cloak>
        <template x-for="janela in lista" :key="janela.id">
            <section class="jn-janela" x-show="! janela.minimizada" role="dialog" :aria-label="janela.titulo">
                <header class="jn-topo">
                    <span class="jn-titulo" x-text="janela.titulo"></span>
                    <button type="button" class="jn-botao" title="Diminuir" aria-label="Diminuir" x-on:click="minimizar(janela.id)">−</button>
                    <button type="button" class="jn-botao" title="Fechar" aria-label="Fechar" x-on:click="fechar(janela.id)">×</button>
                </header>
                <iframe class="jn-frame" :data-janela="janela.id" :src="janela.url" :title="janela.titulo"></iframe>
            </section>
        </template>

        <nav class="jn-barra" x-show="lista.length > 0" aria-label="Janelas abertas">
            <template x-for="janela in lista" :key="'barra-' + janela.id">
                <div class="jn-item" :data-ativa="! janela.minimizada">
                    <button type="button" class="jn-item-titulo" x-text="janela.titulo" x-on:click="janela.minimizada ? restaurar(janela.id) : minimizar(janela.id)"></button>
                    <button type="button" class="jn-botao" title="Fechar" aria-label="Fechar" x-on:click="fechar(janela.id)">×</button>
                </div>
            </template>
        </nav>
    </div>
@endunless
