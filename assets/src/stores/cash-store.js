import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "../services/notify";
import { PrinterService } from "@/services/printer-service";
import { useUserStore } from "@/stores/user-store";
import { useFormat } from "@/composables/useFormat";

export const useCashStore = defineStore("cash", {
  state: () => ({
    isOpen: false,
    statusChecked: false,
    register: null,
    lastClosing: null,
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
          this.lastClosing = res.data.last_closing || null;
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

    // Apenas UMA versão de closeRegister (a completa)
    async closeRegister(closingBalance, notes, breakdown = null) {
      const userStore = useUserStore();
      const { formatDate } = useFormat();

      // Prepara dados para impressão
      const expectedCash = this.summary.cash_balance || 0;
      const countedCash = parseFloat(closingBalance);
      const diff = countedCash - expectedCash;

      const methodsArray = Object.entries(this.summary.methods || {}).map(
        ([key, val]) => ({
          method: key,
          amount: val,
        }),
      );

      const printData = {
        operator: userStore.user?.name || "Operador",
        opened_at: formatDate(this.register?.opened_at, true),
        opening_balance: parseFloat(this.register?.opening_balance || 0),
        total_in: this.summary.total_in,
        total_out: this.summary.total_out,
        methods_breakdown: methodsArray,
        expected_cash: expectedCash,
        counted_cash: countedCash,
        diff: diff,
        notes: notes,
      };

      try {
        const res = await api.post("/cash/close", {
          closing_balance: closingBalance,
          notes,
          breakdown,
        });

        if (res.data.success) {
          notify("success", "Caixa Fechado", "Sessão encerrada com sucesso.");

          // Dispara impressão
          await PrinterService.printCashClosing(printData);

          this.isOpen = false;
          this.register = null;
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
