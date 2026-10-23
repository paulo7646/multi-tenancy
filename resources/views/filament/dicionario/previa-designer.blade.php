{{--
    Designer da prévia do formulário (modal "Pré-visualizar"):
    - clique direito num campo: alinhar pela sugestão, nova linha e "Ajustar campo"
      (modal com tab page, ordem na tab, largura e altura).
    Os campos têm data-previa-campo (uid) (FormularioBuilder em modo prévia).
    O estado fica em $wire.previa (página); o "Salvar" da prévia grava nos componentes.
--}}
<div
    x-data="{
        menu: { aberto: false, x: 0, y: 0, campo: null },

        ajuste(campo) {
            return (this.$wire.previa || []).find((item) => item.campo === campo) || {}
        },

        abrirMenu(evento) {
            const alvo = evento.target.closest('[data-previa-campo]')

            if (! alvo) {
                return
            }

            evento.preventDefault()

            // não deixa o menu sair da tela
            this.menu = {
                aberto: true,
                campo: alvo.dataset.previaCampo,
                x: Math.min(evento.clientX, window.innerWidth - 240),
                y: Math.min(evento.clientY, window.innerHeight - 170),
            }
        },

        fechar() {
            this.menu.aberto = false
        },

        alternar(opcao) {
            this.$wire.previaAlternar(this.menu.campo, opcao)
            this.fechar()
        },

        ajustar() {
            this.$wire.mountAction('ajustarPrevia', { campo: this.menu.campo })
            this.fechar()
        },

        // resumo ao lado de "Ajustar campo": posição na tab e tamanho
        resumo(campo) {
            const previa = this.$wire.previa || []
            const item = this.ajuste(campo)
            const daTab = previa.filter((i) => i.aba === item.aba)
            const posicao = daTab.findIndex((i) => i.campo === campo) + 1

            return [
                posicao ? posicao + 'º' : null,
                item.largura ? item.largura + 'px' : null,
                item.altura ? 'alt. ' + item.altura + 'px' : null,
            ].filter(Boolean).join(' · ')
        },
    }"
    x-on:contextmenu.window="abrirMenu($event)"
    x-on:keydown.escape.window="fechar()"
    x-on:scroll.window="fechar()"
    class="previa-designer"
>
    <style>
        [data-previa-campo] {
            cursor: context-menu;
            border-radius: 0.5rem;
            outline: 1px dashed transparent;
            outline-offset: 4px;
            transition: outline-color 0.15s;
        }

        [data-previa-campo]:hover {
            outline-color: var(--primary-400);
        }

        .previa-dica {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-500);
        }

        .previa-menu {
            position: fixed;
            z-index: 9999;
            min-width: 14rem;
            padding: 0.25rem;
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.2), 0 0 0 1px rgb(0 0 0 / 0.06);
        }

        .dark .previa-menu {
            background: var(--gray-900);
            box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.5), 0 0 0 1px rgb(255 255 255 / 0.1);
        }

        .previa-menu button {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            text-align: left;
            color: var(--gray-700);
        }

        .dark .previa-menu button {
            color: var(--gray-200);
        }

        .previa-menu button:hover {
            background: var(--gray-50);
        }

        .dark .previa-menu button:hover {
            background: rgb(255 255 255 / 0.05);
        }

        .previa-menu .previa-marca {
            color: var(--primary-600);
            font-weight: 600;
        }

        .previa-menu .previa-detalhe {
            color: var(--gray-400);
            font-size: 0.75rem;
        }

        .previa-menu hr {
            margin: 0.25rem 0;
            border-color: var(--gray-100);
        }

        .dark .previa-menu hr {
            border-color: rgb(255 255 255 / 0.1);
        }
    </style>

    <p class="previa-dica">
        <x-filament::icon icon="heroicon-m-cursor-arrow-rays" style="width: 1rem; height: 1rem;" />
        Botão direito num campo: layout, tab page, ordem e tamanho. "Salvar" grava no formulário.
    </p>

    <template x-teleport="body">
        <div
            x-show="menu.aberto"
            x-cloak
            x-transition.opacity.duration.100ms
            x-on:click.outside="fechar()"
            x-on:contextmenu.prevent
            class="previa-menu"
            x-bind:style="`top: ${menu.y}px; left: ${menu.x}px`"
            role="menu"
        >
            <button type="button" role="menuitemcheckbox" x-on:click="alternar('completar')" x-bind:aria-checked="!! ajuste(menu.campo).completar">
                <span>Alinhar pela sugestão</span>
                <span class="previa-marca" x-show="ajuste(menu.campo).completar">✓</span>
            </button>

            <button type="button" role="menuitemcheckbox" x-on:click="alternar('quebra')" x-bind:aria-checked="!! ajuste(menu.campo).quebra">
                <span>Nova linha</span>
                <span class="previa-marca" x-show="ajuste(menu.campo).quebra">✓</span>
            </button>

            <hr>

            <button type="button" role="menuitem" x-on:click="ajustar()">
                <span>Ajustar campo…</span>
                <span class="previa-detalhe" x-text="resumo(menu.campo)"></span>
            </button>
        </div>
    </template>
</div>
