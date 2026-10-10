import { createApp } from 'vue';
import { makeVuetify } from './vuetify';
import { registerComponents } from './index';

/**
 * Monta um ambiente Vue no elemento #app.
 *
 * Os dados enviados pelo Laravel (spa.blade.php) chegam como JSON em
 * data-page e são repassados como props para o App.vue do ambiente.
 */
export function mountApp(App, { theme = {}, el = '#app' } = {}) {
    const root = document.querySelector(el);
    const props = JSON.parse(root?.dataset.page ?? '{}');

    const app = createApp(App, props);

    app.use(makeVuetify(theme));
    registerComponents(app);

    app.mount(root);

    return app;
}
