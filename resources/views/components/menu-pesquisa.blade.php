{{--
    Pesquisa do menu lateral: filtra módulos (grupos) e telas (itens) enquanto se digita. Só aparece com o menu aberto.
    Grupos com resultado abrem sozinhos; ao limpar, o menu volta como estava.
--}}
<div
    x-data="{
        busca: '',
        recolhidosAntes: null,

        normalizar(texto) {
            return (texto || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        },

        filtrar() {
            const termo = this.normalizar(this.busca);
            const menu = $store.sidebar;
            const grupos = document.querySelectorAll('.fi-sidebar-nav .fi-sidebar-group');

            if (termo === '') {
                grupos.forEach((grupo) => {
                    grupo.style.display = '';
                    grupo.querySelectorAll('.fi-sidebar-item').forEach((item) => (item.style.display = ''));
                });

                if (this.recolhidosAntes !== null) {
                    menu.collapsedGroups = this.recolhidosAntes;
                    this.recolhidosAntes = null;
                }

                return;
            }

            if (this.recolhidosAntes === null) {
                this.recolhidosAntes = [...menu.collapsedGroups];
            }

            grupos.forEach((grupo) => {
                const rotulo = grupo.querySelector('.fi-sidebar-group-label')?.textContent.trim() ?? '';
                const grupoCasa = this.normalizar(rotulo).includes(termo);
                let algum = false;

                grupo.querySelectorAll('.fi-sidebar-item').forEach((item) => {
                    const casa = grupoCasa || this.normalizar(item.textContent).includes(termo);
                    item.style.display = casa ? '' : 'none';
                    algum = algum || casa;
                });

                grupo.style.display = algum ? '' : 'none';

                // grupo com resultado abre sozinho
                if (algum && rotulo !== '') {
                    menu.collapsedGroups = menu.collapsedGroups.filter((g) => g !== rotulo);
                }
            });
        },
    }"
    x-show="$store.sidebar.isOpen"
    style="padding: .25rem .75rem .5rem"
>
    <x-filament::input.wrapper inline-prefix prefix-icon="heroicon-m-magnifying-glass">
        <x-filament::input
            type="search"
            id="menu-pesquisa"
            aria-label="Pesquisar no menu"
            autocomplete="off"
            x-model="busca"
            x-on:input="filtrar()"
            x-on:keydown.escape="busca = ''; filtrar()"
        />
    </x-filament::input.wrapper>
</div>
