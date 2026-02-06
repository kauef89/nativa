import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";

export const usePurchasesStore = defineStore("purchases", {
  state: () => ({
    locations: [],
    pendingItems: [],
    library: [],
    isLoading: false,
  }),

  getters: {
    itemsByLocation: (state) => (locationId) => {
      if (!locationId) return [];
      return state.pendingItems.filter(
        (item) =>
          item.valid_locations && item.valid_locations.includes(locationId),
      );
    },
    unassignedItems: (state) =>
      state.pendingItems.filter(
        (item) => !item.valid_locations || item.valid_locations.length === 0,
      ),
    missingLibrary: (state) =>
      state.library
        .filter((item) => item.is_pending)
        .sort((a, b) => a.name.localeCompare(b.name)),
    availableLibrary: (state) =>
      state.library
        .filter((item) => !item.is_pending)
        .sort((a, b) => a.name.localeCompare(b.name)),
  },

  actions: {
    async fetchList() {
      this.isLoading = true;
      try {
        const { data } = await api.get("/purchases/list");
        if (data.success) {
          this.locations = data.locations;
          this.pendingItems = data.items;
          this.library = data.library;
        }
      } catch (e) {
        notify("error", "Erro", "Não foi possível carregar a lista.");
      } finally {
        this.isLoading = false;
      }
    },

    async createLocation(name, type) {
      try {
        const { data } = await api.post("/purchases/location", { name, type });
        if (data.success) {
          notify("success", "Fornecedor", `Local "${name}" criado.`);
          await this.fetchList();
          return data.location_id;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao criar local.");
      }
      return null;
    },

    async toggleNeed(item) {
      const previousState = item.is_pending;
      item.is_pending = !item.is_pending;

      if (previousState && item.list_id) {
        // REMOVER
        try {
          await api.delete("/purchases/item", { data: { id: item.list_id } });
          item.list_id = null;
          this.pendingItems = this.pendingItems.filter(
            (p) => p.insumo_id !== item.id,
          );
        } catch (e) {
          item.is_pending = true; // Rollback
          notify("error", "Erro", "Falha ao remover item.");
        }
      } else {
        // ADICIONAR
        try {
          const payload = {
            name: item.name,
            qty: 1,
            locations: item.valid_locations,
          };

          const { data } = await api.post("/purchases/item", payload);

          if (data.success) {
            item.list_id = data.list_id;
            await this.fetchList();
          }
        } catch (e) {
          item.is_pending = false; // Rollback
          notify("error", "Erro", "Falha ao adicionar item.");
        }
      }
    },

    async registerItem(payload) {
      try {
        const { data } = await api.post("/purchases/item", payload);
        if (data.success) {
          notify("success", "Cadastrado", "Novo insumo registrado.");
          await this.fetchList();
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao cadastrar.");
      }
      return false;
    },

    async buyItem(itemId, locationId, cost) {
      try {
        const { data } = await api.post("/purchases/buy", {
          id: itemId,
          location_id: locationId,
          cost: cost,
        });
        if (data.success) {
          this.pendingItems = this.pendingItems.filter((i) => i.id !== itemId);
          const libItem = this.library.find((l) => l.list_id === itemId);
          if (libItem) {
            libItem.is_pending = false;
            libItem.list_id = null;
          }
          notify("success", "Comprado", "Item baixado da lista.");
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao baixar item.");
      }
    },
  },
});
