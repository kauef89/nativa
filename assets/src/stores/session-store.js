import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "@/services/notify"; // <--- Import da Ponte

export const useSessionStore = defineStore("session", {
  state: () => ({
    sessionId: null,
    sessionType: null, // 'table', 'counter', 'delivery'
    currentTable: null, // Só para mesas
    clientName: null, // Nome do Cliente (Balcão/Delivery)
    isLoading: false,

    // Itens e Totais
    orderItems: [],
    orderTotal: 0.0,
  }),

  getters: {
    hasOpenSession: (state) => !!state.sessionId,
    // Getter útil para exibir no cabeçalho
    sessionLabel: (state) => {
      if (state.currentTable) return `Mesa ${state.currentTable}`;
      if (state.clientName) return `${state.clientName}`;
      return "Venda Avulsa";
    },
  },

  actions: {
    // Abre uma nova sessão (Mesa, Balcão ou Delivery)
    async openSession(type, tableNumber = null, clientName = null) {
      this.isLoading = true;
      try {
        const payload = {
          type: type,
          table_number: tableNumber,
        };

        if (clientName) {
          payload.delivery_address_json = { name: clientName };
        }

        const response = await api.post("/open-session", payload);

        if (response.data.success) {
          this.sessionId = response.data.session_id;
          this.sessionType = type;
          this.currentTable = tableNumber;
          this.clientName = clientName;

          this.fetchOrderSummary();

          // Notificação de Sucesso via PrimeVue
          notify(
            "success",
            "Sessão Aberta",
            `Venda iniciada #${this.sessionId}`
          );
          return true;
        }
      } catch (error) {
        console.error("Erro ao abrir sessão:", error);
        // O interceptor da API já exibe o erro, mas se cair aqui por outro motivo:
        notify("error", "Erro", "Não foi possível iniciar a venda.");
        return false;
      } finally {
        this.isLoading = false;
      }
    },

    // Retoma uma sessão existente (ex: Clicar numa mesa ocupada no mapa)
    async resumeSession(sessionId, tableNumber) {
      this.isLoading = true;
      this.sessionId = sessionId;
      this.currentTable = tableNumber;

      // Aqui poderíamos buscar o nome do cliente se a API retornasse na listagem de mesas
      // Por enquanto, apenas carrega os itens
      await this.fetchOrderSummary();

      this.isLoading = false;
    },

    // Busca o extrato (itens lançados)
    async fetchOrderSummary() {
      if (!this.sessionId) return;
      try {
        const response = await api.get("/session-items", {
          params: { session_id: this.sessionId },
        });

        if (response.data.success) {
          this.orderItems = response.data.items;
          this.orderTotal = response.data.total;
        }
      } catch (error) {
        console.error("Erro ao buscar conta:", error);
      }
    },

    // Limpa o estado local ao sair da mesa
    leaveSession() {
      this.sessionId = null;
      this.sessionType = null;
      this.currentTable = null;
      this.clientName = null;
      this.orderItems = [];
      this.orderTotal = 0;
    },
  },
});
