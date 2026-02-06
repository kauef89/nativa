import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "../services/notify";
import router from "../router";

export const useSessionStore = defineStore("session", {
  state: () => ({
    sessionId: null,
    sessionType: null,
    identifier: null, // Pode ser Número da Mesa ou Nome do Cliente
    sessionStatus: null, // Novo: Status da sessão (new, preparing, etc)
    accounts: ["Principal"],
    currentAccount: "Principal",
    items: [],
    paidItems: [],
    transactions: [],
    groupedItems: { Principal: [] },
    totals: { total: 0, subtotal: 0, discount: 0 },
    isLoading: false,
    selectedItemsForPayment: [],
  }),

  getters: {
    hasActiveSession: (state) => !!state.sessionId,
    hasOpenSession: (state) => !!state.sessionType,
    currentAccountItems: (state) =>
      state.groupedItems[state.currentAccount] || [],
    accountTotal: (state) => (accountName) => {
      const items = state.groupedItems[accountName] || [];
      return items.reduce((sum, item) => sum + parseFloat(item.line_total), 0);
    },
    totalSelectedForPayment: (state) => {
      return state.selectedItemsForPayment.reduce(
        (sum, item) => sum + item.valueToPay,
        0,
      );
    },
  },

  actions: {
    setAccount(accountName) {
      if (this.accounts.includes(accountName)) {
        this.currentAccount = accountName;
      }
    },

    async createAccount(name, isCommand = false, targetSessionId = null) {
      const idToUse = targetSessionId || this.sessionId || 0;
      const tableNum = this.identifier;

      // Permite criar sem ID apenas se for Mesa (identifier numérico)
      if (!idToUse && !tableNum) {
        console.error(
          "[SessionStore] Impossível criar conta: Sem ID de sessão.",
        );
        notify("error", "Erro", "Sessão inválida.");
        return false;
      }

      try {
        const res = await api.post("/create-account", {
          session_id: idToUse,
          table_number: tableNum, // Pode ser null para delivery
          name: name,
          is_command: isCommand,
        });

        if (res.data.success) {
          if (res.data.session_id && !this.sessionId) {
            this.sessionId = res.data.session_id;
          }

          if (this.sessionId === res.data.session_id) {
            this.accounts = res.data.accounts;
            this.currentAccount = name;
            if (!this.groupedItems[name]) this.groupedItems[name] = [];
          }

          notify("success", "Conta Criada", `Conta '${name}' adicionada.`);
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Erro ao criar conta.");
        console.error(e);
        return false;
      }
    },

    async openSession(type, identifier, redirect = true) {
      this.isLoading = true;
      try {
        this.sessionId = null;
        this.sessionType = type;
        this.identifier = identifier;
        this.accounts = ["Principal"];
        this.currentAccount = "Principal";
        this.items = [];
        this.transactions = [];
        this.groupedItems = { Principal: [] };
        this.selectedItemsForPayment = [];

        if (type === "table") {
          await this.checkExistingSession(identifier);
        }

        if (redirect) {
          router.push("/staff/pdv");
        }
      } catch (e) {
        console.error("[NATIVA] Erro em openSession:", e);
      } finally {
        this.isLoading = false;
      }
    },

    async resumeSession(sessionId, identifier = null, redirect = true) {
      this.sessionId = sessionId;
      // Se tiver identificador explícito (ex: clique no grid), usa. Senão, espera o refresh.
      if (identifier) this.identifier = identifier;
      
      // CORREÇÃO: Removemos a linha 'this.sessionType = table'.
      // Agora o refreshSession vai buscar o tipo correto do backend.
      
      await this.refreshSession();

      if (redirect) {
        router.push("/staff/pdv");
      }
    },

    async refreshSession() {
      if (!this.sessionId) return;
      this.isLoading = true;
      try {
        const res = await api.get(
          `/session-items?session_id=${this.sessionId}`,
        );
        if (res.data.success) {
          
          // --- CORREÇÃO: Atualiza o estado com os dados do Backend ---
          if (res.data.session_info) {
              this.sessionType = res.data.session_info.type;
              this.identifier = res.data.session_info.identifier;
              this.sessionStatus = res.data.session_info.status;
          }

          this.items = res.data.items;
          this.paidItems = res.data.paid_items || [];
          this.transactions = res.data.transactions || [];
          this.accounts = res.data.accounts || ["Principal"];
          this.groupedItems = res.data.grouped_items || { Principal: [] };
          this.totals.total = res.data.total;

          // Limpeza de seleção: remove itens que não existem mais (trocados/cancelados)
          const activeIds = this.items.map((i) => i.id);
          this.selectedItemsForPayment = this.selectedItemsForPayment.filter(
            (selection) => activeIds.includes(selection.itemId),
          );

          if (!this.accounts.includes(this.currentAccount)) {
            this.currentAccount = this.accounts.includes("Principal")
              ? "Principal"
              : this.accounts[0];
          }
        }
      } catch (e) {
        console.error("Erro ao atualizar sessão:", e);
        if (e.response && e.response.status === 404) {
          this.sessionId = null;
        }
      } finally {
        this.isLoading = false;
      }
    },

    async addItem(product, qty = 1, modifiers = [], subAccount = null) {
      const targetAccount = subAccount || this.currentAccount;
      
      // Para mesas, enviamos table_number para criação lazy.
      // Para delivery/outros, enviamos apenas session_id (já deve existir).
      const payload = {
        session_id: this.sessionId || 0,
        product_id: product.id,
        qty,
        modifiers,
        sub_account: targetAccount,
        table_number: (this.sessionType === 'table' && !this.sessionId) ? this.identifier : null,
        type: this.sessionType || "table",
      };

      try {
        const res = await api.post("/add-item", payload);
        if (res.data.success) {
          if (res.data.session_id && !this.sessionId) {
            this.sessionId = res.data.session_id;
          }
          await this.refreshSession();
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao adicionar item.");
      }
    },

    async checkExistingSession(tableNum) {
      if (!tableNum) return;
      try {
        const res = await api.get("/tables-status");
        if (res.data.success) {
          const table = res.data.tables.find((t) => t.number == tableNum);
          if (table && table.status === "occupied" && table.sessionId) {
            this.sessionId = table.sessionId;
            await this.refreshSession();
          }
        }
      } catch (e) {}
    },

    leaveSession() {
      this.sessionId = null;
      this.sessionType = null;
      this.identifier = null;
      this.sessionStatus = null;
      this.accounts = ["Principal"];
      this.currentAccount = "Principal";
      this.items = [];
      this.paidItems = [];
      this.transactions = [];
      this.groupedItems = { Principal: [] };
      this.totals = { total: 0, subtotal: 0, discount: 0 };
      this.selectedItemsForPayment = [];
    },
  },
});