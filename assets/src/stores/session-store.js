import { defineStore } from "pinia";
import api from "../services/api";
import { notify } from "../services/notify";
import router from "../router";

export const useSessionStore = defineStore("session", {
  state: () => ({
    sessionId: null,
    sessionType: null, // 'table', 'counter'
    identifier: null,
    accounts: ["Principal"],
    currentAccount: "Principal",
    items: [],
    groupedItems: { Principal: [] },
    totals: { total: 0, subtotal: 0, discount: 0 },
    isLoading: false,
  }),

  getters: {
    // CORREÇÃO: Getter que faltava.
    // Retorna TRUE se tivermos um tipo definido (mesmo que o ID ainda seja null/lazy)
    hasOpenSession: (state) => !!state.sessionType,

    currentAccountItems: (state) =>
      state.groupedItems[state.currentAccount] || [],
    accountTotal: (state) => (accountName) => {
      const items = state.groupedItems[accountName] || [];
      return items.reduce((sum, item) => sum + parseFloat(item.line_total), 0);
    },
  },

  actions: {
    async openSession(type, identifier) {
      console.log("[NATIVA FRONT] openSession chamado", { type, identifier });
      this.isLoading = true;
      try {
        // Reset do estado
        this.sessionId = null;
        this.sessionType = type;
        this.identifier = identifier;
        this.accounts = ["Principal"];
        this.currentAccount = "Principal";
        this.items = [];
        this.groupedItems = { Principal: [] };

        // Verifica mesa se necessário
        if (type === "table") {
          await this.checkExistingSession(identifier);
        }

        // Navegação
        console.log("[NATIVA FRONT] Navegando para /pdv");
        router.push("/pdv");
      } catch (e) {
        console.error("[NATIVA FRONT] Erro em openSession:", e);
      } finally {
        this.isLoading = false;
      }
    },

    async resumeSession(sessionId, identifier = null) {
      this.sessionId = sessionId;
      if (identifier) this.identifier = identifier;
      this.sessionType = "table";
      await this.refreshSession();
      router.push("/pdv");
    },

    async refreshSession() {
      if (!this.sessionId) return;
      this.isLoading = true;
      try {
        const res = await api.get(
          `/session-items?session_id=${this.sessionId}`,
        );
        if (res.data.success) {
          this.items = res.data.items;
          this.accounts = res.data.accounts || ["Principal"];
          this.groupedItems = res.data.grouped_items || { Principal: [] };
          this.totals.total = res.data.total;

          if (!this.accounts.includes(this.currentAccount)) {
            this.currentAccount = "Principal";
          }
        }
      } catch (e) {
        console.error("Erro refreshSession", e);
        if (e.response && e.response.status === 404) {
          this.sessionId = null; // Sessão expirou
        }
      } finally {
        this.isLoading = false;
      }
    },

    setAccount(accountName) {
      if (this.accounts.includes(accountName)) {
        this.currentAccount = accountName;
      }
    },

    async createAccount(name, isCommand = false) {
      if (!this.sessionId) return false;
      try {
        const res = await api.post("/create-account", {
          session_id: this.sessionId,
          name: name,
          is_command: isCommand,
        });
        if (res.data.success) {
          this.accounts = res.data.accounts;
          this.currentAccount = name;
          if (!this.groupedItems[name]) this.groupedItems[name] = [];
          notify("success", "Conta Criada", `Conta '${name}' adicionada.`);
          return true;
        }
      } catch (e) {
        notify("error", "Erro", "Erro ao criar conta.");
        return false;
      }
    },

    async addItem(product, qty = 1, modifiers = [], subAccount = null) {
      const targetAccount = subAccount || this.currentAccount;

      const payload = {
        session_id: this.sessionId || 0,
        product_id: product.id,
        qty,
        modifiers,
        sub_account: targetAccount,
        table_number: !this.sessionId ? this.identifier : null,
        type: this.sessionType || "table",
      };

      try {
        const res = await api.post("/add-item", payload);
        if (res.data.success) {
          // Captura o ID da sessão criada (Lazy Creation)
          if (res.data.session_id && !this.sessionId) {
            this.sessionId = res.data.session_id;
          }
          await this.refreshSession();
          return true;
        }
      } catch (e) {
        console.error(e);
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
      } catch (e) {
        console.error(e);
      }
    },

    leaveSession() {
      this.sessionId = null;
      this.sessionType = null;
      this.identifier = null;
      this.accounts = ["Principal"];
      this.currentAccount = "Principal";
      this.items = [];
      this.groupedItems = { Principal: [] };
      this.totals = { total: 0, subtotal: 0, discount: 0 };
    },
  },
});
