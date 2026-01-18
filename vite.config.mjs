import { fileURLToPath, URL } from "node:url";
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import Components from "unplugin-vue-components/vite";
import { PrimeVueResolver } from "unplugin-vue-components/resolvers";

export default defineConfig({
  // 1. IMPORTANTE: Define o caminho público absoluto para os assets no navegador
  // Isso corrige o erro 404 do CSS e JS
  base: "/wp-content/plugins/nativa/assets/dist/",

  plugins: [
    vue(),
    Components({
      resolvers: [PrimeVueResolver()],
    }),
  ],

  resolve: {
    alias: {
      // 2. Aponta para 'assets/src' (já que src é filha de assets)
      "@": fileURLToPath(new URL("./assets/src", import.meta.url)),
    },
  },

  build: {
    // 3. A saída continua em 'assets/dist'
    outDir: "assets/dist",
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      // 4. O ponto de entrada agora busca dentro de assets/src
      input: "assets/src/main.js",
    },
  },

  server: {
    port: 3000,
    strictPort: true,
    hmr: {
      host: "localhost",
    },
  },
});
