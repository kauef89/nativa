import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';

// 1. Importar CSS Global (Tailwind) PRIMEIRO
import './style.css'; 

// 2. Depois os Ícones
import '@fortawesome/fontawesome-free/css/all.css';

// 3. Configuração do PrimeVue
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import { definePreset } from '@primevue/themes';

// --- CONFIGURAÇÃO DO TEMA (Mantém igual) ---
const BerinjelaPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#ede7f6',
            100: '#d1c4e9',
            200: '#b39ddb',
            300: '#9575cd',
            400: '#7e57c2',
            500: '#673ab7', 
            600: '#5e35b1',
            700: '#512da8',
            800: '#4527a0',
            900: '#311b92',
            950: '#1a0f55'
        },
        colorScheme: {
            light: {
                surface: {
                    0: '#ffffff',
                    50: '#fafafa',
                    100: '#f4f4f5',
                    200: '#e4e4e7',
                    300: '#d4d4d8',
                    400: '#a1a1aa',
                    500: '#71717a',
                    600: '#52525b',
                    700: '#3f3f46',
                    800: '#27272a',
                    900: '#18181b',
                    950: '#09090b'
                }
            },
            dark: {
                surface: {
                    0: '#ffffff',
                    50: '#fafafa',
                    100: '#f4f4f5',
                    200: '#e4e4e7',
                    300: '#d4d4d8',
                    400: '#a1a1aa',
                    500: '#71717a',
                    600: '#52525b',
                    700: '#3f3f46',
                    800: '#27272a',
                    900: '#18181b',
                    950: '#09090b'
                }
            }
        }
    }
});

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Instala PrimeVue
app.use(PrimeVue, {
    theme: {
        preset: BerinjelaPreset,
        options: {
            darkModeSelector: '.app-dark', 
            cssLayer: {
                name: 'primevue', // Isso joga o PrimeVue na camada definida no style.css
                order: 'tailwind-base, primevue, tailwind-utilities'
            }
        }
    },
    ripple: true
});

app.use(ToastService);
app.mount('#nativa-app');