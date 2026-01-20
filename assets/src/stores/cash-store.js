import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "../services/notify";

export const useCashStore = defineStore("cash", {
  state: () => ({
    isOpen: false,
    statusChecked: false,
    register: null,
    lastClosing: null, // <--- Armazena os dados do último fechamento (vindo do DB)
    summary: { cash_balance: 0, total_in: 0, total_out: 0 },
    transactions: [],
    isLoading: false,
  }),

  actions: {
    async checkStatus() {
      this.isLoading = true;
      try {
        const res = await api.get("/cash/status");
        if (res.data.status === "open") {
          this.isOpen = true;
          this.register = res.data.register;
          this.summary = res.data.summary;
          this.lastClosing = null;
        } else {
          this.isOpen = false;
          this.register = null;
          this.lastClosing = res.data.last_closing || null; // <--- Pega o mapa salvo no banco
        }
        this.statusChecked = true;
      } catch (e) {
        console.error(e);
        this.statusChecked = true;
      } finally {
        this.isLoading = false;
      }
    },

    async openRegister(amount) {
      try {
        const res = await api.post("/cash/open", { amount });
        if (res.data.success) {
          notify("success", "Caixa Aberto", "Boas vendas!");
          await this.checkStatus();
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Não foi possível abrir o caixa.");
      }
      return false;
    },

    async addMovement(type, amount, description) {
      try {
        const res = await api.post("/cash/move", { type, amount, description });
        if (res.data.success) {
          notify(
            "success",
            "Registrado",
            type === "bleed" ? "Sangria realizada." : "Suprimento adicionado.",
          );
          await this.checkStatus();
          await this.fetchLedger();
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao registrar movimentação.");
      }
      return false;
    },

    // ATUALIZADO: Recebe o breakdown para salvar no banco
    async closeRegister(closingBalance, notes, breakdown = null) {
      try {
        const res = await api.post("/cash/close", {
          closing_balance: closingBalance,
          notes,
          breakdown, // Envia { bills: [], coins: [] } para a API
        });

        if (res.data.success) {
          notify("success", "Caixa Fechado", "Sessão encerrada com sucesso.");
          this.isOpen = false;
          this.register = null;
          // Atualiza o status para já pegar esse fechamento como "último" se reabrir em seguida
          await this.checkStatus();
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao fechar caixa.");
      }
      return false;
    },

    async fetchLedger() {
      try {
        const res = await api.get("/cash/ledger");
        this.transactions = res.data.transactions;
      } catch (e) {
        console.error(e);
      }
    },
  },
});
