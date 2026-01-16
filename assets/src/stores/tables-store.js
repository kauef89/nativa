import { defineStore } from "pinia";
import api from "../services/api";

export const useTablesStore = defineStore("tables", {
  state: () => ({
    tables: [], // Array de objetos { number: 1, status: 'free', sessionId: null }
    isLoading: false,
    totalTables: 20, // Defina quantas mesas seu restaurante tem
  }),

  actions: {
    async fetchTables() {
      this.isLoading = true;
      try {
        // 1. Gera o grid padrão (todas livres)
        const grid = [];
        for (let i = 1; i <= this.totalTables; i++) {
          grid.push({ number: i, status: "free", sessionId: null });
        }

        // 2. Busca as ocupadas do servidor
        const response = await api.get("/tables-status");

        if (response.data.success) {
          const occupied = response.data.occupied_tables;

          // 3. Cruza os dados: Marca as ocupadas no grid
          occupied.forEach((occ) => {
            const tableIndex = grid.findIndex(
              (t) => t.number == occ.table_number
            );
            if (tableIndex !== -1) {
              grid[tableIndex].status = "occupied";
              grid[tableIndex].sessionId = occ.session_id;
            }
          });
        }

        this.tables = grid;
      } catch (error) {
        console.error("Erro ao buscar mesas:", error);
      } finally {
        this.isLoading = false;
      }
    },
  },
});
