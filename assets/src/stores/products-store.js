import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";

export const useProductsStore = defineStore("products", {
  state: () => ({
    products: [],
    isLoading: false,
    filter: "",
    categoryFilter: [],
  }),

  getters: {
    filteredProducts: (state) => {
      let temp = [...state.products];
      if (state.filter) {
        const lower = state.filter.toLowerCase();
        temp = temp.filter(
          (p) =>
            p.name.toLowerCase().includes(lower) ||
            (p.sku && p.sku.toLowerCase().includes(lower)),
        );
      }
      if (state.categoryFilter && state.categoryFilter.length > 0) {
        temp = temp.filter((p) =>
          state.categoryFilter.includes(p.category_name),
        );
      }
      return temp;
    },

    uniqueCategories: (state) => {
      const cats = state.products.map((p) => p.category_name).filter(Boolean);
      return [...new Set(cats)].sort();
    },
  },

  actions: {
    async fetchProducts() {
      this.isLoading = true;
      try {
        const { data } = await api.get("/products");
        if (data.success) {
          this.products = data.products;
        }
      } catch (e) {
        console.error("Erro ao buscar produtos", e);
        notify("error", "Erro", "Falha ao carregar produtos.");
      } finally {
        this.isLoading = false;
      }
    },

    async updateProduct(id, payload) {
      try {
        const { data } = await api.post(
          `/products/${id}/quick-update`,
          payload,
        );
        if (data.success) {
          const index = this.products.findIndex((p) => p.id === id);
          if (index !== -1) {
            this.products[index] = { ...this.products[index], ...payload };
          }
          notify("success", "Atualizado", "Produto salvo com sucesso.");
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao atualizar produto.");
        return false;
      }
    },

    async bulkUpdateProducts(ids, payload) {
      if (!ids.length) return false;
      try {
        const { data } = await api.post("/products/bulk-update", {
          ids,
          data: payload,
        });
        if (data.success) {
          this.products.forEach((p) => {
            if (ids.includes(p.id)) {
              if (payload.availability) p.availability = payload.availability;
              if (payload.stock_quantity !== undefined)
                p.stock_quantity = payload.stock_quantity;
              if (payload.manage_stock !== undefined)
                p.manage_stock = payload.manage_stock;
            }
          });
          const count = data.updated_count || ids.length;
          notify("success", "Sucesso", `${count} produtos atualizados.`);
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha na atualização em massa.");
        return false;
      }
    },

    // --- CSV ACTIONS ---

    async exportCSV() {
      try {
        const { data } = await api.get("/products/export");
        if (data.success && data.csv_content) {
          const byteCharacters = atob(data.csv_content);
          const byteNumbers = new Array(byteCharacters.length);
          for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
          }
          const byteArray = new Uint8Array(byteNumbers);
          const blob = new Blob([byteArray], {
            type: "text/csv;charset=utf-8;",
          });

          const link = document.createElement("a");
          const url = URL.createObjectURL(blob);
          link.setAttribute("href", url);
          link.setAttribute("download", data.filename || "produtos.csv");
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);

          notify("success", "Exportado", "Download iniciado.");
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao exportar CSV.");
      }
    },

    async importCSV(file) {
      const formData = new FormData();
      formData.append("file", file);

      try {
        const { data } = await api.post("/products/import", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        if (data.success) {
          notify("success", "Importado", data.message);
          await this.fetchProducts(); // Recarrega a lista
          return true;
        }
      } catch (e) {
        notify(
          "error",
          "Erro",
          e.response?.data?.message || "Falha na importação.",
        );
      }
      return false;
    },
  },
});
