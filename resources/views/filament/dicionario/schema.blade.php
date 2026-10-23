{{--
    Diagrama ER das tabelas do dicionário (modal "Schema" da lista de tabelas).
    $codigo: texto Mermaid "erDiagram" (App\Sistema\Runtime\DiagramaSchema).
    O Mermaid é carregado da CDN só quando o modal abre.
--}}
<div
    x-data="{
        codigo: @js($codigo),
        zoom: 1,
        erro: null,
        carregando: true,

        async iniciar() {
            if (! this.codigo) {
                this.carregando = false

                return
            }

            try {
                if (! window.mermaid) {
                    await new Promise((ok, falha) => {
                        const script = document.createElement('script')
                        script.src = 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.min.js'
                        script.onload = ok
                        script.onerror = () => falha(new Error('Não foi possível carregar o Mermaid (sem internet?).'))
                        document.head.appendChild(script)
                    })
                }

                const escuro = document.documentElement.classList.contains('dark')

                window.mermaid.initialize({
                    startOnLoad: false,
                    securityLevel: 'strict',
                    theme: escuro ? 'dark' : 'default',
                    themeVariables: { fontFamily: 'inherit', fontSize: '13px' },
                    er: { layoutDirection: 'TB', useMaxWidth: false, entityPadding: 12, minEntityWidth: 140 },
                })

                const { svg } = await window.mermaid.render('schema-' + Math.random().toString(36).slice(2), this.codigo)
                this.$refs.desenho.innerHTML = svg
            } catch (e) {
                this.erro = e.message ?? String(e)
            } finally {
                this.carregando = false
            }
        },

        mudarZoom(fator) {
            this.zoom = Math.min(3, Math.max(0.2, +(this.zoom * fator).toFixed(2)))
        },

        baixar() {
            const svg = this.$refs.desenho.querySelector('svg')

            if (! svg) {
                return
            }

            const link = document.createElement('a')
            link.href = URL.createObjectURL(new Blob([svg.outerHTML], { type: 'image/svg+xml' }))
            link.download = 'schema.svg'
            link.click()
            URL.revokeObjectURL(link.href)
        },
    }"
    x-init="iniciar()"
    class="schema-dicionario"
>
    <style>
        .schema-dicionario .schema-barra {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.8125rem;
            color: var(--gray-500);
        }

        .schema-dicionario .schema-botoes {
            display: flex;
            gap: 0.25rem;
        }

        .schema-dicionario .schema-botoes button {
            min-width: 2rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            color: var(--gray-700);
            box-shadow: 0 0 0 1px rgb(0 0 0 / 0.1);
        }

        .dark .schema-dicionario .schema-botoes button {
            color: var(--gray-200);
            box-shadow: 0 0 0 1px rgb(255 255 255 / 0.15);
        }

        .schema-dicionario .schema-botoes button:hover {
            background: var(--gray-50);
        }

        .dark .schema-dicionario .schema-botoes button:hover {
            background: rgb(255 255 255 / 0.05);
        }

        .schema-dicionario .schema-area {
            overflow: auto;
            max-height: 72vh;
            padding: 1rem;
            border-radius: 0.75rem;
            background-color: var(--gray-50);
            background-image: radial-gradient(rgb(0 0 0 / 0.08) 1px, transparent 1px);
            background-size: 16px 16px;
        }

        .dark .schema-dicionario .schema-area {
            background-color: rgb(255 255 255 / 0.02);
            background-image: radial-gradient(rgb(255 255 255 / 0.08) 1px, transparent 1px);
        }

        .schema-dicionario .schema-desenho {
            width: max-content;
        }

        .schema-dicionario .schema-aviso {
            padding: 2rem;
            text-align: center;
            color: var(--gray-500);
        }
    </style>

    <div class="schema-barra">
        <span>
            PK chave · FK chave estrangeira · UK único · * obrigatório.
            Setas: tabela pai → tabelas filhas (o texto da seta é a coluna FK).
        </span>

        <div class="schema-botoes">
            <button type="button" x-on:click="mudarZoom(0.8)" title="Diminuir">−</button>
            <button type="button" x-on:click="zoom = 1" x-text="Math.round(zoom * 100) + '%'" title="Tamanho original"></button>
            <button type="button" x-on:click="mudarZoom(1.25)" title="Aumentar">+</button>
            <button type="button" x-on:click="baixar()" title="Baixar como imagem (SVG)">Baixar SVG</button>
        </div>
    </div>

    <div class="schema-area">
        <div class="schema-aviso" x-show="carregando">Desenhando o schema…</div>
        <div class="schema-aviso" x-show="! codigo" x-cloak>Nenhuma tabela gerada ainda.</div>
        <div class="schema-aviso" x-show="erro" x-text="'Não foi possível desenhar: ' + erro" x-cloak></div>

        {{-- "zoom" (e não transform) para a área de rolagem acompanhar o tamanho --}}
        <div wire:ignore x-ref="desenho" class="schema-desenho" x-bind:style="`zoom: ${zoom}`"></div>
    </div>
</div>
