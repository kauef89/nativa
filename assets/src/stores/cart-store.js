import { defineStore } from "pinia";
import { useSessionStore } from "./session-store";
import { useUserStore } from "./user-store";
import api from "../services/api";
import { notify } from "@/services/notify";
import router from "@/router";

export const useCartStore = defineStore("cart", {
  state: () => ({
    items: [],
    isOpen: false,
    isSending: false,
    // Estado para Checkout
    checkoutForm: {
      address: null, // Objeto de endereço selecionado
      paymentMethod: null, // 'pix', 'card', 'cash'
      changeFor: 0,
      coupon: "",
      notes: "",
    },
  }),

  getters: {
    totalValue: (state) => {
      return state.items.reduce((acc, item) => acc + item.price * item.qty, 0);
    },
    totalItems: (state) => {
      return state.items.reduce((acc, item) => acc + item.qty, 0);
    },
  },

  actions: {
    addItem(product) {
      const existing = this.items.find(
        (i) =>
          i.id === product.id &&
          JSON.stringify(i.modifiers) === JSON.stringify(product.modifiers),
      );
      if (existing) {
        existing.qty++;
      } else {
        this.items.push({
          ...product,
          qty: 1,
          uniqueId: Date.now() + Math.random(),
        });
      }
      this.isOpen = true;
    },

    updateItem(index, newItem) {
      this.items[index] = newItem;
    },

    increaseQty(index) {
      this.items[index].qty++;
    },

    decreaseQty(index) {
      if (this.items[index].qty > 1) {
        this.items[index].qty--;
      } else {
        this.items.splice(index, 1);
      }
    },

    async checkoutWeb() {
      const userStore = useUserStore();

      if (this.items.length === 0) return;
      if (!userStore.isLoggedIn) {
        notify(
          "warn",
          "Login Necessário",
          "Entre na sua conta para finalizar.",
        );
        return;
      }
      if (!this.checkoutForm.address && !this.checkoutForm.isPickup) {
        notify("warn", "Endereço", "Selecione um endereço de entrega.");
        return;
      }

      this.isSending = true;

      try {
        const payload = {
          items: this.items.map((i) => ({
            id: i.id,
            qty: i.qty,
            modifiers: i.modifiers,
          })),
          address: this.checkoutForm.address,
          payment: {
            method: this.checkoutForm.paymentMethod,
            change_for: this.checkoutForm.changeFor,
          },
          coupon: this.checkoutForm.coupon,
          notes: this.checkoutForm.notes,
          source: "app_web_v2",
        };

        const { data } = await api.post("/place-web-order", payload);

        if (data.success) {
          notify(
            "success",
            "Sucesso!",
            "Seu pedido foi realizado. Acompanhe o status.",
          );
          this.items = [];
          this.checkoutForm = {
            address: null,
            paymentMethod: null,
            changeFor: 0,
            coupon: "",
            notes: "",
          };
          this.isOpen = false;
        } else {
          throw new Error(data.message || "Erro desconhecido.");
        }
      } catch (error) {
        console.error(error);
        const msg = error.response?.data?.message || error.message;
        notify("error", "Não foi possível enviar", msg);
      } finally {
        this.isSending = false;
      }
    },

    async sendOrder() {
      const sessionStore = useSessionStore();

      // [SONDA 4] Auditoria antes do envio
      console.log("--- [AUDIT CART] INICIO DO ENVIO ---");
      console.log("Conta Atual na SessionStore:", sessionStore.currentAccount);
      console.log("ID da Sessão:", sessionStore.sessionId);
      console.log("Itens no Carrinho:", JSON.parse(JSON.stringify(this.items)));

      if (this.items.length === 0) return;

      if (sessionStore.sessionType === "table" && !sessionStore.identifier) {
        notify("warn", "Atenção", "Número da mesa não identificado.");
        return;
      }

      this.isSending = true;

      try {
        const itemsToSend = [...this.items];

        // 1. Lógica de Criação (Lazy) - Primeiro item cria a sessão
        if (!sessionStore.sessionId) {
          const firstItem = itemsToSend.shift();

          // [SONDA 5-A] Payload do Lazy Creation
          const payloadFirst = {
            session_id: 0,
            product_id: firstItem.id,
            qty: firstItem.qty,
            modifiers: firstItem.modifiers,
            sub_account: sessionStore.currentAccount, // <--- Verifica se isso é 'Maria' ou 'Principal'
            table_number: sessionStore.identifier,
            type: sessionStore.sessionType || "table",
            client_name: sessionStore.clientName,
          };

          console.log(
            "[AUDIT CART] Enviando PRIMEIRO item (Lazy Create):",
            payloadFirst,
          );

          const res = await api.post("/add-item", payloadFirst);

          if (res.data.success && res.data.session_id) {
            sessionStore.sessionId = res.data.session_id;
            console.log(
              "[AUDIT CART] Sessão Criada. Novo ID:",
              sessionStore.sessionId,
            );
          } else {
            throw new Error("Falha ao criar sessão no servidor.");
          }
        }

        // 2. Envia o restante dos itens
        if (itemsToSend.length > 0) {
          const promises = itemsToSend.map((item) => {
            // [SONDA 5-B] Payload dos demais itens
            const payload = {
              session_id: sessionStore.sessionId,
              product_id: item.id,
              qty: item.qty,
              modifiers: item.modifiers,
              sub_account: sessionStore.currentAccount, // <--- Verifica se isso é 'Maria' ou 'Principal'
            };

            console.log("[AUDIT CART] Enviando item subsequente:", payload);

            return api.post("/add-item", payload);
          });
          await Promise.all(promises);
        }

        notify("success", "Pedido Enviado", "Enviado para produção! 🍳");

        await sessionStore.refreshSession();
        this.items = [];
        this.isOpen = false;

        if (sessionStore.sessionType === "table") {
          router.push("/tables");
        } else if (sessionStore.sessionType === "delivery") {
          router.push("/delivery");
        }
      } catch (error) {
        console.error(error);
        notify(
          "error",
          "Erro no Envio",
          "Não foi possível processar o pedido.",
        );
      } finally {
        this.isSending = false;
        console.log("--- [AUDIT CART] FIM DO ENVIO ---");
      }
    },
  },
});
