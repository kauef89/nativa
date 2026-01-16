import { defineStore } from "pinia";
import { useSessionStore } from "./session-store";
import api from "../services/api";
import { notify } from "@/services/notify"; // <--- Import da Ponte

export const useCartStore = defineStore("cart", {
  state: () => ({
    items: [],
    isOpen: false,
    isSending: false,
  }),

  getters: {
    totalItems: (state) => state.items.reduce((acc, item) => acc + item.qty, 0),
    totalValue: (state) =>
      state.items.reduce((acc, item) => {
        return acc + parseFloat(item.price || 0) * item.qty;
      }, 0),
  },

  actions: {
    // Adiciona item ao carrinho
    addItem(product, modifiersFromArg = null) {
      // Se modifiers vierem no 2º argumento (estilo antigo), usa eles.
      // Se não, tenta pegar de dentro do produto (estilo modal novo).
      const modifiers = modifiersFromArg || product.modifiers || [];

      // Se o produto já veio com preço calculado (do modal), usa ele.
      // Se não, calcula base + extras.
      let finalPrice = product.price;
      if (modifiersFromArg) {
        const extras = modifiers.reduce(
          (acc, m) => acc + parseFloat(m.price || 0),
          0
        );
        finalPrice = parseFloat(product.price) + extras;
      }

      // Garante ID único para a lista (timestamp)
      const uniqueId = product.uniqueId || Date.now();

      // Monta o objeto do carrinho
      const cartItem = {
        ...product,
        id: product.id,
        uniqueId: uniqueId,
        name: product.name,
        image: product.image,
        qty: 1,
        price: parseFloat(finalPrice),
        modifiers: modifiers,
      };

      this.items.push(cartItem);
      this.isOpen = true; // Abre a sidebar do carrinho automaticamente

      // Feedback visual rápido (Opcional, mas bom para UX)
      notify("info", "Item Adicionado", `${product.name} foi para o carrinho.`);
    },

    // Atualiza um item já existente (edição)
    updateItem(index, updatedItem) {
      const original = this.items[index];
      this.items[index] = {
        ...updatedItem,
        uniqueId: original.uniqueId,
        qty: original.qty,
      };
      this.isOpen = true;
      notify("info", "Item Atualizado", "Alterações salvas no carrinho.");
    },

    removeItem(index) {
      this.items.splice(index, 1);
    },

    increaseQty(index) {
      this.items[index].qty++;
    },

    decreaseQty(index) {
      if (this.items[index].qty > 1) {
        this.items[index].qty--;
      } else {
        this.removeItem(index);
      }
    },

    // Envia o pedido para a API
    async sendOrder() {
      const sessionStore = useSessionStore();

      // Validação de segurança
      if (!sessionStore.sessionId) {
        notify(
          "warn",
          "Atenção",
          "Nenhuma sessão aberta para enviar o pedido!"
        );
        return;
      }

      this.isSending = true;
      try {
        // Cria um array de Promises para enviar todos os itens
        const promises = this.items.map((item) => {
          return api.post("/add-item", {
            session_id: sessionStore.sessionId,
            product_id: item.id,
            qty: item.qty,
            modifiers: item.modifiers,
          });
        });

        // Aguarda todos serem processados
        await Promise.all(promises);

        // Sucesso!
        notify("success", "Pedido Enviado", "Enviado para produção! 🍳");

        // Atualiza o ticket da mesa e limpa o carrinho
        sessionStore.fetchOrderSummary();
        this.items = [];
        this.isOpen = false;
      } catch (error) {
        console.error(error);
        notify(
          "error",
          "Erro no Envio",
          "Alguns itens não puderam ser enviados."
        );
      } finally {
        this.isSending = false;
      }
    },
  },
});
