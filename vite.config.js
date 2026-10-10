import {
    defineConfig
} from 'vite';
import fs from 'node:fs';
import path from 'node:path';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

/**
 * Cada ambiente Vue fica em /views e tem seu próprio main.js:
 *  - views/multitenant/main.js       -> sistema principal (domínio central)
 *  - views/clientes/{slug}/main.js   -> uma pasta por empresa (tenant)
 */
function vueEntries() {
    const entries = ['views/multitenant/main.js'];
    const clientesDir = path.resolve(__dirname, 'views/clientes');

    if (fs.existsSync(clientesDir)) {
        for (const slug of fs.readdirSync(clientesDir)) {
            const entry = `views/clientes/${slug}/main.js`;
            if (fs.existsSync(path.resolve(__dirname, entry))) {
                entries.push(entry);
            }
        }
    }

    return entries;
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', ...vueEntries()],
            refresh: ['resources/views/**', 'routes/**', 'views/**'],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({ autoImport: true }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@components': path.resolve(__dirname, 'views/components'),
            '@views': path.resolve(__dirname, 'views'),
        },
    },
    build: {
        // Não apaga public/build antes de compilar: o build automático
        // (BuildFrontendAssets) roda com o site no ar, e as outras empresas
        // continuam funcionando até o manifest novo ser gravado no final.
        emptyOutDir: false,
    },
    server: {
        cors: true,
    },
});
