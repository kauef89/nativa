import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "@/services/notify";

export const useDeliveryStore = defineStore("delivery", {
  state: () => ({
    // --- 1. DADOS DE ENDEREÇO E FRETE ---
    customerName: "",
    customerPhone: "",

    selectedStreet: null,
    streetNumber: "",
    complement: "",
    reference: "",

    calculatedBairro: null,
    deliveryFee: 0.0,
    freeShippingThreshold: 0.0,

    isSearching: false,
    isCalculating: false,
    streetSuggestions: [],

    // Modal de Cadastro Rápido
    showCreateStreetModal: false,
    newStreetName: "",
    availableBairros: [],
    isCreatingStreet: false,

    // --- 2. GESTÃO DE PEDIDOS / KANBAN ---
    orders: [],
    isLoadingOrders: false,
    lastFetch: null,
  }),

  getters: {
    isValidAddress: (state) => {
      return (
        !!state.selectedStreet &&
        !!state.streetNumber &&
        !!state.calculatedBairro
      );
    },

    finalFee: (state) => state.deliveryFee,

    pendingOrders: (state) =>
      state.orders.filter((o) =>
        ["novo", "pendente", "wc-pending"].includes(o.status.toLowerCase()),
      ),
    kitchenOrders: (state) =>
      state.orders.filter((o) =>
        ["preparando", "wc-processing"].includes(o.status.toLowerCase()),
      ),
    deliveringOrders: (state) =>
      state.orders.filter((o) =>
        ["entrega", "saiu para entrega", "enviado", "em rota"].includes(
          o.status.toLowerCase(),
        ),
      ),
    completedOrders: (state) =>
      state.orders.filter((o) =>
        ["concluido", "entregue", "wc-completed"].includes(
          o.status.toLowerCase(),
        ),
      ),
  },

  actions: {
    // --- ACTIONS DE ENDEREÇO ---

    async searchStreets(query) {
      if (!query || query.length < 3) return;
      try {
        const response = await api.get(`/search-address?q=${query}`);
        if (response.data.success) {
          this.streetSuggestions = response.data.results;
        }
      } catch (error) {
        console.error("Erro ao buscar ruas", error);
      }
    },

    async calculateFreight() {
      if (!this.selectedStreet || !this.streetNumber) return;
      if (this.selectedStreet.id === "new_street") return;

      this.isCalculating = true;
      this.calculatedBairro = null;

      try {
        const response = await api.post("/calculate-freight", {
          rua_id: this.selectedStreet.id,
          numero: this.streetNumber,
        });

        if (response.data.success) {
          this.calculatedBairro = response.data.bairro;
          this.deliveryFee = parseFloat(response.data.taxa);
          this.freeShippingThreshold = parseFloat(
            response.data.frete_gratis_acima_de,
          );
          notify(
            "success",
            "Endereço Localizado",
            `Bairro: ${this.calculatedBairro}`,
          );
        } else {
          notify(
            "warn",
            "Atenção",
            response.data.message || "Endereço fora da área de entrega.",
          );
        }
      } catch (error) {
        notify("error", "Erro", "Falha ao calcular taxa de entrega.");
        console.error(error);
      } finally {
        this.isCalculating = false;
      }
    },

    reset() {
      this.customerName = "";
      this.customerPhone = "";
      this.selectedStreet = null;
      this.streetNumber = "";
      this.complement = "";
      this.reference = "";
      this.calculatedBairro = null;
      this.deliveryFee = 0;
      this.streetSuggestions = [];
    },

    async fetchBairros() {
      if (this.availableBairros.length > 0) return;
      try {
        const response = await api.get("/bairros");
        if (response.data.success) {
          this.availableBairros = response.data.bairros;
        }
      } catch (e) {
        console.error("Erro ao buscar lista de bairros", e);
      }
    },

    async createStreet(bairroId) {
      if (!this.newStreetName || !bairroId) return;

      this.isCreatingStreet = true;
      try {
        const response = await api.post("/create-street", {
          name: this.newStreetName,
          bairro_id: bairroId,
        });

        if (response.data.success) {
          notify("success", "Sucesso", "Rua cadastrada e vinculada!");
          this.showCreateStreetModal = false;
          this.selectedStreet = response.data.rua;

          setTimeout(() => {
            const numInput = document.querySelector('input[placeholder="Nº"]');
            if (numInput) numInput.focus();
          }, 200);
        }
      } catch (error) {
        console.error(error);
        notify("error", "Erro", "Não foi possível cadastrar a rua.");
      } finally {
        this.isCreatingStreet = false;
      }
    },

    // --- ACTIONS DE GESTÃO DE PEDIDOS ---

    async fetchOrders() {
      this.isLoadingOrders = true;
      try {
        const { data } = await api.get("/orders");
        if (data.success) {
          this.orders = data.orders;
          this.lastFetch = new Date();
        }
      } catch (e) {
        console.error("Erro ao buscar pedidos delivery", e);
      } finally {
        this.isLoadingOrders = false;
      }
    },

    async updateOrderStatus(orderId, newStatus) {
      const orderIndex = this.orders.findIndex((o) => o.id === orderId);
      if (orderIndex === -1) return;

      const oldStatus = this.orders[orderIndex].status;

      // Feedback visual imediato
      let displayStatus = "Atualizando...";
      if (newStatus === "preparando") displayStatus = "Preparando";
      if (newStatus === "entrega") displayStatus = "Em Rota";
      if (newStatus === "concluido") displayStatus = "Concluído";

      this.orders[orderIndex].status = displayStatus;

      try {
        const { data } = await api.post("/update-order-status", {
          order_id: orderId,
          status: newStatus,
        });

        if (data.success) {
          notify(
            "success",
            "Status Atualizado",
            `Pedido #${orderId} atualizado.`,
          );
          this.orders[orderIndex].status = data.new_status;
        } else {
          throw new Error(data.message);
        }
      } catch (e) {
        this.orders[orderIndex].status = oldStatus;
        notify("error", "Erro", "Não foi possível atualizar o status.");
      }
    },

    // --- AGORA DENTRO DAS ACTIONS ---

    async updateOrderAddress(orderId, addressData) {
      try {
        const { data } = await api.post("/update-order-address", {
          order_id: orderId,
          address: addressData,
        });

        if (data.success) {
          notify("success", "Sucesso", "Endereço de entrega alterado.");
          const idx = this.orders.findIndex((o) => o.id === orderId);
          if (idx !== -1) {
            const formatted = `${addressData.street}, ${addressData.number} - ${addressData.district}`;
            this.orders[idx].address = formatted;
          }
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao atualizar endereço.");
      }
      return false;
    },

    async cancelOrder(orderId) {
      try {
        const { data } = await api.post("/update-order-status", {
          order_id: orderId,
          status: "cancelado",
        });

        if (data.success) {
          notify("success", "Cancelado", `Pedido #${orderId} foi cancelado.`);
          this.orders = this.orders.filter((o) => o.id !== orderId);
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Não foi possível cancelar.");
      }
      return false;
    },
  },
});
