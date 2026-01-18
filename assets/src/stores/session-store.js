import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "@/services/notify";

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
    /**
     * Abre uma nova sessão.
     * @param {string} type - 'table', 'counter' ou 'delivery'
     * @param {number|null} tableNumber - Número da mesa (se houver)
     * @param {object|string|null} deliveryData - Objeto de endereço (Delivery) ou String de nome (Balcão)
     */
    async openSession(type, tableNumber = null, deliveryData = null) {
      this.isLoading = true;
      try {
        const payload = {
          type: type,
          table_number: tableNumber,
        };

        // LÓGICA HÍBRIDA: Aceita Objeto Completo (Delivery) ou String Simples (Balcão)
        if (deliveryData && typeof deliveryData === "object") {
          // Caso Delivery V2: Envia o objeto completo de endereço
          payload.delivery_address_json = deliveryData;
        } else if (deliveryData && typeof deliveryData === "string") {
          // Caso Balcão/Legado: Envia apenas o nome envelopado
          payload.delivery_address_json = { name: deliveryData };
        }

        const response = await api.post("/open-session", payload);

        if (response.data.success) {
          this.sessionId = response.data.session_id;
          this.sessionType = type;
          this.currentTable = tableNumber;

          // Define o nome do cliente na memória para exibição
          if (deliveryData && typeof deliveryData === "object") {
            this.clientName = deliveryData.name;
          } else {
            this.clientName = deliveryData;
          }

          this.fetchOrderSummary();

          // Notificação de Sucesso
          notify(
            "success",
            "Sessão Aberta",
            `Venda iniciada #${this.sessionId}`,
          );
          return true;
        }
      } catch (error) {
        console.error("Erro ao abrir sessão:", error);
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

      // CORREÇÃO CRUCIAL: Definir explicitamente o tipo como 'table'
      // Isso garante que o TablesView saiba que deve mostrar o POS e não o mapa
      this.sessionType = "table";

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

    // Limpa o estado local ao sair da mesa ou finalizar
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
