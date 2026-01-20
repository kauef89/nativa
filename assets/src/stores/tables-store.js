import { defineStore } from "pinia";
import api from "../services/api";

export const useTablesStore = defineStore("tables", {
  state: () => ({
    tables: [],
    isLoading: false,
    zones: [], // Lista de zonas únicas
  }),

  getters: {
    // Agrupa as mesas por zona para renderizar no grid
    tablesByZone: (state) => {
      const groups = {};
      state.tables.forEach((table) => {
        const z = table.zone || "Geral";
        if (!groups[z]) groups[z] = [];
        groups[z].push(table);
      });
      return groups;
    },
  },

  actions: {
    async fetchTables() {
      this.isLoading = true;
      try {
        const response = await api.get("/tables-status");
        if (response.data.success) {
          this.tables = response.data.tables;
          // Extrai zonas únicas
          this.zones = [...new Set(this.tables.map((t) => t.zone || "Geral"))];
        }
      } catch (error) {
        console.error("Erro ao buscar mesas:", error);
      } finally {
        this.isLoading = false;
      }
    },
  },
});
