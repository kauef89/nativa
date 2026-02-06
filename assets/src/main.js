import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";
import { OneSignalService } from "./services/onesignal";

import "./style.css";
import "./overrides.css";
import "@fortawesome/fontawesome-free/css/all.css";

import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import ToastService from "primevue/toastservice";
import { definePreset } from "@primevue/themes";

// --- MATERIAL YOU (NATIVA BLUE STONE) ---
const MaterialYouPreset = definePreset(Aura, {
  semantic: {
    primary: {
      // Paleta Blue (Azul Padrão - #3b82f6 no 500)
      50: "#eff6ff",
      100: "#dbeafe",
      200: "#bfdbfe",
      300: "#93c5fd",
      400: "#60a5fa",
      500: "#3b82f6", // Cor Principal (Royal Blue)
      600: "#2563eb",
      700: "#1d4ed8",
      800: "#1e40af",
      900: "#1e3a8a",
      950: "#172554",
    },
    colorScheme: {
      dark: {
        surface: {
          // Escala Stone (Cinza Quente - Mantida)
          0: "#ffffff",
          50: "#0c0a09", // L0
          100: "#1c1917", // L1
          200: "#292524", // L2
          300: "#44403c", // L3
          400: "#57534e", // L4
          500: "#78716c",
          600: "#a8a29e",
          700: "#d6d3d1",
          800: "#e7e5e4", // Texto
          900: "#f5f5f4",
          950: "#fafaf9",
        },
        formField: {
          background: "{surface.100}",
          borderColor: "transparent",
          color: "{surface.800}",
          focusBorderColor: "transparent",
          hoverBorderColor: "transparent",
          focusRing: {
            width: "0",
            style: "none",
            color: "transparent",
            offset: "0",
          },
        },
      },
    },
  },
  components: {
    inputtext: {
      colorScheme: {
        dark: {
          root: {
            background: "{surface.100}",
            borderColor: "transparent",
            color: "{surface.900}",
            borderRadius: "16px",
          },
        },
      },
    },
    button: {
      root: {
        borderRadius: "24px",
      },
    },
    card: {
      colorScheme: {
        dark: {
          root: {
            background: "{surface.50}",
            borderColor: "transparent",
            borderRadius: "24px",
            shadow: "none",
          },
        },
      },
    },
  },
});

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

app.use(PrimeVue, {
  theme: {
    preset: MaterialYouPreset,
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
OneSignalService.init();
app.mount("#nativa-app");
