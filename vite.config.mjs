import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import Components from "unplugin-vue-components/vite";
import { PrimeVueResolver } from "unplugin-vue-components/resolvers";
import path from "path";

export default defineConfig({
  plugins: [
    vue(),
    Components({
      resolvers: [PrimeVueResolver()],
    }),
  ],
  resolve: {
    alias: {
      // CORREÇÃO AQUI: Apontando para assets/src
      "@": path.resolve(__dirname, "./assets/src"),
    },
  },
  build: {
    outDir: "assets/dist",
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      // O ponto de entrada também deve estar correto
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
