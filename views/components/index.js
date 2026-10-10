import AppLayout from './AppLayout.vue';
import PageHeader from './PageHeader.vue';

/**
 * Componentes padrão, compartilhados por todos os ambientes.
 * Ficam registrados globalmente, então podem ser usados em qualquer
 * .vue sem import: <AppLayout>, <PageHeader>...
 */
export const components = {
    AppLayout,
    PageHeader,
};

export function registerComponents(app) {
    for (const [name, component] of Object.entries(components)) {
        app.component(name, component);
    }
}

export { AppLayout, PageHeader };
