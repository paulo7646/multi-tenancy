import 'vuetify/styles';
import '@mdi/font/css/materialdesignicons.css';
import { createVuetify } from 'vuetify';

const defaultTheme = {
    dark: false,
    colors: {
        primary: '#F59E0B',
        secondary: '#1F2937',
    },
};

/**
 * Cria a instância do Vuetify. Cada ambiente pode sobrescrever as cores
 * passando seu próprio tema (ver views/clientes/{slug}/theme.js).
 */
export function makeVuetify(theme = {}) {
    return createVuetify({
        theme: {
            defaultTheme: 'app',
            themes: {
                app: {
                    ...defaultTheme,
                    ...theme,
                    colors: { ...defaultTheme.colors, ...(theme.colors ?? {}) },
                },
            },
        },
    });
}
