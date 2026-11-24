{{--
    Gerenciador de janelas: telas do sistema abertas em iframe, com X (fecha) e − (minimiza para a barra
    inferior). A janela minimizada só some da tela: o iframe continua vivo, com o que foi digitado.
    Máximo de {{ \App\Sistema\Runtime\Janela::LIMITE }} janelas; a mais antiga fecha quando entra a seguinte.
    Dentro de uma janela (?janela=1) não há gerenciador: ele fica só na página principal.
--}}
@unless (\App\Sistema\Runtime\Janela::ativa())
    <style>
        .jn-raiz { --jn-fundo: light-dark(#ffffff, #18181b); --jn-borda: light-dark(#e4e4e7, #3f3f46); --jn-texto: light-dark(#18181b, #fafafa); --jn-suave: light-dark(#f4f4f5, #27272a); }
        .jn-janela { position: fixed; inset: 0 0 56px 0; margin: auto; width: min(56rem, 94vw); height: min(38rem, calc(100vh - 7rem)); z-index: 5; display: flex; flex-direction: column; background: var(--jn-fundo); color: var(--jn-texto); border: 1px solid var(--jn-borda); border-radius: .75rem; box-shadow: 0 25px 50px -12px rgb(0 0 0 / .45); overflow: hidden; }
        /* telas do menu: ocupam a área de conteúdo (entre o menu lateral, a barra de cima e a barra de baixo); a posição vem do JS */
        .jn-janela[data-tamanho='grande'] { margin: 0; width: auto; height: auto; border-radius: 0; box-shadow: none; border-width: 0; }
        .jn-janela[data-tamanho='grande'] .jn-topo { padding-block: .25rem; }
        .jn-topo { display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; background: var(--jn-suave); border-bottom: 1px solid var(--jn-borda); }
        .jn-titulo { flex: 1; font-weight: 600; font-size: .875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .jn-botao { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: .375rem; border: 0; background: transparent; color: inherit; cursor: pointer; font-size: 1.125rem; line-height: 1; }
        .jn-botao:hover { background: var(--jn-borda); }
        .jn-frame { flex: 1; width: 100%; border: 0; background: var(--jn-fundo); }
        .jn-barra { position: fixed; left: 0; right: 0; bottom: 0; z-index: 6; display: flex; gap: .5rem; padding: .5rem .75rem; background: var(--jn-suave); border-top: 1px solid var(--jn-borda); overflow-x: auto; }
        /* com a barra na tela, o conteúdo ganha folga embaixo para não ficar escondido atrás dela */
        html.jn-com-barra .fi-main { padding-bottom: 3.5rem; }
        /* modal de confirmação: texto centralizado e legível; botões lado a lado, e um embaixo do outro em tela estreita */
        .jn-confirmar-texto { display: block; text-align: center; text-wrap: balance; line-height: 1.5; overflow-wrap: anywhere; }
        .jn-confirmar-botoes { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; width: 100%; }
        .jn-confirmar-botoes > * { flex: 1 1 11rem; justify-content: center; }
        .jn-fim { margin-inline-start: auto; display: flex; align-items: center; gap: .5rem; }
        .jn-item { display: inline-flex; align-items: center; gap: .25rem; max-width: 16rem; padding: .25rem .25rem .25rem .75rem; border: 1px solid var(--jn-borda); border-radius: .5rem; background: var(--jn-fundo); color: var(--jn-texto); font-size: .8125rem; }
        .jn-item[data-ativa='true'] { outline: 2px solid #f59e0b; }
        .jn-item-titulo { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; background: none; border: 0; color: inherit; padding: 0; }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('janelasGerenciador', () => ({
                lista: [],
                limite: {{ \App\Sistema\Runtime\Janela::LIMITE }},

                area: { top: 0, left: 0, barra: 48 },

                // área livre do painel: abaixo da barra de cima, à direita do menu lateral e acima da barra de baixo
                medir() {
                    const menu = document.querySelector('.fi-sidebar');
                    const topo = document.querySelector('.fi-topbar');
                    const barra = this.$root.querySelector('.jn-barra');
                    const menuVisivel = menu && menu.getBoundingClientRect().width > 0 && getComputedStyle(menu).display !== 'none';

                    this.area = {
                        top: topo ? Math.max(0, topo.getBoundingClientRect().bottom) : 0,
                        left: menuVisivel ? Math.max(0, menu.getBoundingClientRect().right) : 0,
                        barra: barra ? barra.getBoundingClientRect().height || 48 : 48,
                    };
                },

                // objeto (não texto): assim o Alpine não apaga o display:none de x-show ao minimizar
                estilo(janela) {
                    if (janela.tamanho !== 'grande') {
                        return {};
                    }

                    return { inset: `${this.area.top}px 0 ${this.area.barra}px ${this.area.left}px` };
                },

                init() {
                    // dentro do iframe de uma janela não há gerenciador: ele fica só na página principal
                    if (window.self !== window.top) {
                        this.$root.remove();

                        return;
                    }

                    this.medir();
                    window.addEventListener('resize', () => this.medir());

                    // tema (claro/escuro): as telas dentro das janelas seguem o da página principal
                    const enviarTema = (alvo) => alvo?.contentWindow?.postMessage({ tipo: 'tema', escuro: document.documentElement.classList.contains('dark') }, window.location.origin);

                    new MutationObserver(() => this.$root.querySelectorAll('iframe[data-janela]').forEach(enviarTema))
                        .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

                    this.$root.addEventListener('load', (evento) => {
                        if (evento.target.matches?.('iframe[data-janela]')) {
                            enviarTema(evento.target);
                            this.atualizarTitulo(evento.target);
                        }
                    }, true);

                    // recolher ou abrir o menu lateral muda a área livre: a barra e as janelas acompanham
                    const menu = document.querySelector('.fi-sidebar');

                    if (menu && window.ResizeObserver) {
                        new ResizeObserver(() => this.medir()).observe(menu);
                    }
                    this.$watch('lista', () => this.$nextTick(() => this.medir()));

                    window.Janelas = {
                        abrir: (dados) => this.abrir(dados),
                        fechar: (id) => this.fechar(id),
                        lista: () => this.lista,
                    };

                    // itens do menu lateral abrem a tela como janela (Ctrl/Shift/botão do meio abrem normal)
                    const inicio = @js(rtrim(filament()->getUrl() ?? '', '/'));

                    document.addEventListener('click', (evento) => {
                        const link = evento.target.closest?.('.fi-sidebar a[href]');

                        if (! link || evento.defaultPrevented || evento.button !== 0 || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.altKey || link.target === '_blank') {
                            return;
                        }

                        let destino;

                        try {
                            destino = new URL(link.href, window.location.href);
                        } catch (erro) {
                            return;
                        }

                        // o início (painel) navega normal; só telas do próprio sistema viram janela
                        if (destino.origin !== window.location.origin || destino.href.replace(/\/$/, '') === inicio || destino.hash !== '' && destino.pathname === window.location.pathname) {
                            return;
                        }

                        evento.preventDefault();
                        evento.stopPropagation();

                        destino.searchParams.set('janela', '1');
                        this.abrir({ titulo: link.textContent.trim().replace(/\s+/g, ' ') || 'Janela', url: destino.href, origem: destino.pathname, tamanho: 'grande' });
                    }, true);

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

                // título da barra de baixo = o da tela que a janela está mostrando (Visualização de Empresa, Gerenciamento de Empresas...)
                atualizarTitulo(quadro) {
                    const janela = this.lista.find((j) => j.id === quadro.dataset.janela);

                    if (! janela || janela.tamanho !== 'grande') {
                        return;
                    }

                    try {
                        const doc = quadro.contentDocument;
                        const cabecalho = doc.querySelector('.fi-header-heading')?.textContent.trim().replace(/\s+/g, ' ');
                        const pagina = cabecalho || doc.title.split(' - ')[0].trim();

                        if (pagina) {
                            janela.titulo = pagina;
                        }
                    } catch (erro) {
                        // tela de outra origem: mantém o título
                    }
                },

                abrir({ titulo, url, id, tamanho, origem }) {
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

                    if (origem) {
                        // item do menu: cada clique abre uma janela nova, mesmo igual às abertas (até o limite da barra)
                        id = `${origem}#${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
                    } else {
                        id = id || url;

                        if (this.lista.some((j) => j.id === id)) {
                            return this.restaurar(id);
                        }
                    }

                    const nova = { id, titulo: titulo || 'Janela', url, minimizada: false, tamanho: tamanho || 'pequeno', origem: origem || null };
                    const antiga = this.lista[0];

                    // a barra está cheia: a mais antiga vai fechar. Se for formulário de inclusão ou alteração, mostra ela e pergunta
                    if (this.lista.length >= this.limite && antiga && this.temAlteracao(antiga)) {
                        this.restaurar(antiga.id);

                        this.confirmar({
                            titulo: 'Fechar a janela mais antiga?',
                            texto: `O limite é de ${this.limite} janelas e a mais antiga ("${antiga.titulo}") tem dados alterados e não salvos. Deseja mesmo fechá-la sem salvar os dados?`,
                            sim: 'Sim, fechar sem salvar',
                        }).then((ok) => {
                            if (ok) {
                                this.fechar(antiga.id);
                                this.inserir(nova);
                            }
                        });

                        return;
                    }

                    this.inserir(nova);
                },

                inserir(nova) {
                    this.lista.forEach((j) => (j.minimizada = true));
                    this.lista.push(nova);

                    // passou do limite: a mais antiga sai
                    while (this.lista.length > this.limite) {
                        this.lista.shift();
                    }
                },

                // a janela está num formulário de inclusão ou alteração (.../create ou .../{id}/edit) com dados alterados e não salvos?
                temAlteracao(janela) {
                    try {
                        const quadro = this.quadro(janela.id)?.contentWindow;

                        return /\/(create|edit)\/?$/.test(quadro?.location.pathname ?? '') && quadro.jnAlterado === true;
                    } catch (erro) {
                        return false;
                    }
                },

                // fecha todas; se houver formulário com alteração não salva, pergunta antes
                async fecharTodas() {
                    if (this.lista.some((j) => this.temAlteracao(j)) && ! await this.confirmar({
                        titulo: 'Fechar todas as janelas?',
                        texto: 'Há formulários com dados alterados e não salvos. Deseja mesmo fechar todas as janelas sem salvar os dados?',
                        sim: 'Sim, fechar todas',
                    })) {
                        return;
                    }

                    this.lista = [];
                },

                // pergunta no modal do Filament (Promise: true = confirmou, false = cancelou ou fechou)
                confirmacao: { titulo: '', texto: '', sim: 'Sim', resolver: null },

                confirmar({ titulo, texto, sim }) {
                    return new Promise((resolver) => {
                        this.confirmacao = { titulo, texto, sim: sim || 'Sim', resolver };
                        this.$dispatch('open-modal', { id: 'jn-confirmar' });
                    });
                },

                responder(valor) {
                    const resolver = this.confirmacao.resolver;

                    this.confirmacao.resolver = null;
                    this.$dispatch('close-modal', { id: 'jn-confirmar' });
                    resolver?.(valor);
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

    <div class="jn-raiz" x-data="janelasGerenciador" x-cloak x-effect="document.documentElement.classList.toggle('jn-com-barra', lista.length > 0)">
        <template x-for="janela in lista" :key="janela.id">
            <section class="jn-janela" x-show="! janela.minimizada" role="dialog" :aria-label="janela.titulo" :data-tamanho="janela.tamanho" :style="estilo(janela)">
                <header class="jn-topo">
                    <span class="jn-titulo" x-text="janela.tamanho === 'grande' ? '' : janela.titulo"></span>
                    <button type="button" class="jn-botao" title="Diminuir" aria-label="Diminuir" x-on:click="minimizar(janela.id)">−</button>
                    <button type="button" class="jn-botao" title="Fechar" aria-label="Fechar" x-on:click="fechar(janela.id)">×</button>
                </header>
                <iframe class="jn-frame" :data-janela="janela.id" :src="janela.url" :title="janela.titulo"></iframe>
            </section>
        </template>

        <x-filament::modal id="jn-confirmar" width="md" alignment="center" icon="heroicon-o-exclamation-triangle" icon-color="warning" x-on:modal-closed.window="$event.detail.id === 'jn-confirmar' && confirmacao.resolver && responder(false)">
            <x-slot name="heading"><span x-text="confirmacao.titulo"></span></x-slot>
            <x-slot name="description"><span class="jn-confirmar-texto" x-text="confirmacao.texto"></span></x-slot>

            <x-slot name="footer">
                <div class="jn-confirmar-botoes">
                    <x-filament::button color="warning" x-on:click="responder(true)" x-text="confirmacao.sim"></x-filament::button>
                    <x-filament::button color="gray" outlined x-on:click="responder(false)">Não, voltar</x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        <nav class="jn-barra" x-show="lista.length > 0" :style="{ left: area.left + 'px' }" aria-label="Janelas abertas">
            <template x-for="janela in lista" :key="'barra-' + janela.id">
                <div class="jn-item" :data-ativa="! janela.minimizada">
                    <button type="button" class="jn-item-titulo" x-text="janela.titulo" x-on:click="janela.minimizada ? restaurar(janela.id) : minimizar(janela.id)"></button>
                    <button type="button" class="jn-botao" title="Fechar" aria-label="Fechar" x-on:click="fechar(janela.id)">×</button>
                </div>
            </template>

            <div class="jn-fim">
                <span
                    x-tooltip="{
                        content: `Limite de ${limite} janelas por vez. Ao abrir mais uma, a mais antiga é fechada.`,
                        theme: $store.theme,
                    }"
                >
                    <x-filament::badge size="sm" color="gray" x-show="lista.length < limite">
                        <span x-text="`${lista.length}/${limite}`"></span>
                    </x-filament::badge>

                    <x-filament::badge size="sm" color="warning" x-show="lista.length >= limite">
                        <span x-text="`${lista.length}/${limite}`"></span>
                    </x-filament::badge>
                </span>

                <x-filament::icon-button
                    icon="heroicon-m-x-mark"
                    color="gray"
                    size="sm"
                    label="Fechar todas as janelas"
                    tooltip="Fechar todas as janelas"
                    x-on:click="fecharTodas()"
                />
            </div>
        </nav>
    </div>
@endunless
