import { defineStore } from "pinia";
import api from "../services/api";

export const useMenuStore = defineStore("menu", {
  state: () => ({
    categories: [], // Aqui ficam as categorias (Lanches, Bebidas...)
    isLoading: false,
  }),

  actions: {
    async fetchMenu() {
      // Se já carregou antes, não carrega de novo (Cache simples)
      if (this.categories.length > 0) return;

      this.isLoading = true;
      try {
        const response = await api.get("/menu");
        if (response.data.success) {
          this.categories = response.data.menu;
        }
      } catch (error) {
        console.error("Erro ao buscar cardápio:", error);
      } finally {
        this.isLoading = false;
      }
    },
  },
});
