import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";

// 1. CSS Global (Tailwind)
import "./style.css";

// 2. Ícones
import "@fortawesome/fontawesome-free/css/all.css";

// 3. PrimeVue & Tema
import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import ToastService from "primevue/toastservice";
import { definePreset } from "@primevue/themes";

// --- DEFINIÇÃO DO TEMA: MENTA (Nativa V2) ---
const MentaPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: "#ecfdf5", // Mint muito claro
      100: "#d1fae5",
      200: "#a7f3d0",
      300: "#6ee7b7",
      400: "#34d399",
      500: "#10b981", // <--- COR PRINCIPAL (Emerald-500)
      600: "#059669", // Hover
      700: "#047857",
      800: "#065f46",
      900: "#064e3b",
      950: "#022c22", // Texto escuro sobre fundo Mint
    },
    // Esquema de cores neutras (Mantém Dark Mode limpo)
    colorScheme: {
      light: {
        surface: {
          0: "#ffffff",
          50: "#fafafa",
          100: "#f4f4f5",
          200: "#e4e4e7",
          300: "#d4d4d8",
          400: "#a1a1aa",
          500: "#71717a",
          600: "#52525b",
          700: "#3f3f46",
          800: "#27272a",
          900: "#18181b",
          950: "#09090b",
        },
      },
      dark: {
        surface: {
          0: "#ffffff",
          50: "#fafafa",
          100: "#f4f4f5",
          200: "#e4e4e7",
          300: "#d4d4d8",
          400: "#a1a1aa",
          500: "#71717a",
          600: "#52525b",
          700: "#3f3f46",
          800: "#27272a",
          900: "#18181b",
          950: "#09090b",
        },
      },
    },
  },
});

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Instala PrimeVue com o Preset Menta
app.use(PrimeVue, {
  theme: {
    preset: MentaPreset,
    options: {
      darkModeSelector: ".app-dark",
      cssLayer: {
        name: "primevue",
        order: "tailwind-base, primevue, tailwind-utilities",
      },
    },
  },
  ripple: true,
});

app.use(ToastService);
app.mount("#nativa-app");
