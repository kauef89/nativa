import { defineStore } from "pinia";
import { useSessionStore } from "./session-store";
import { useUserStore } from "./user-store";
import api from "../services/api";
import { notify } from "@/services/notify";
import router from "@/router";
import { PrinterService } from "@/services/printer-service";
import { useFormat } from "@/composables/useFormat";

export const useCartStore = defineStore("cart", {
  state: () => ({
    items: [],
    isOpen: false,
    isSending: false,
    posPaymentPromise: {
      method: null,
      changeFor: null,
    },
    checkoutForm: {
      address: null,
      paymentMethod: null,
      changeFor: 0,
      coupon: "",
      notes: "",
      deliveryFee: 0,
    },
    activeOffer: null,
    offerSheetOpen: false,
    rejectedOffers: [],
    isCheckingOffer: false,
  }),

  getters: {
    totalValue: (state) =>
      state.items.reduce((acc, item) => acc + item.price * item.qty, 0),
    totalItems: (state) => state.items.reduce((acc, item) => acc + item.qty, 0),
  },

  actions: {
    addItem(product) {
      // Verifica se o item já existe (mesmo ID e mesmos modificadores)
      const existing = this.items.find(
        (i) =>
          i.id === product.id &&
          JSON.stringify(i.modifiers) === JSON.stringify(product.modifiers),
      );

      if (existing) {
        existing.qty++;
      } else {
        // CORREÇÃO: Usa o ID da estação vindo do produto (ou 'default')
        // Removemos a verificação rígida === 'fundos'
        const definedStation = product.station || "default";

        this.items.push({
          ...product,
          qty: 1,
          uniqueId: Date.now() + Math.random(),
          station: definedStation,
        });
      }
      this.isOpen = true;
      this.checkUpsell();
    },

    updateItem(index, newItem) {
      // Garante que a estação seja preservada ou atualizada
      if (!newItem.station && this.items[index].station) {
        newItem.station = this.items[index].station;
      }
      this.items[index] = newItem;
      this.checkUpsell();
    },

    increaseQty(index) {
      this.items[index].qty++;
      this.checkUpsell();
    },

    decreaseQty(index) {
      if (this.items[index].qty > 1) {
        this.items[index].qty--;
      } else {
        this.items.splice(index, 1);
      }
    },

    async checkUpsell() {
      if (this.items.length === 0) {
        this.activeOffer = null;
        return;
      }
      if (this.isCheckingOffer || this.offerSheetOpen) return;

      this.isCheckingOffer = true;
      try {
        const cartPayload = this.items.map((i) => ({
          id: i.id,
          qty: i.qty,
          price: i.price,
        }));
        const { data } = await api.post("/check-offer", { cart: cartPayload });

        if (data.has_offer && data.offer) {
          const offerId = data.offer.offer_id;
          if (this.rejectedOffers.includes(offerId)) return;
          const alreadyInCart = this.items.some(
            (i) => i.is_offer && i.offer_source_id === offerId,
          );
          if (alreadyInCart) return;

          this.activeOffer = data.offer;
          this.offerSheetOpen = true;
        }
      } catch (e) {
        console.error("Erro oferta:", e);
      } finally {
        this.isCheckingOffer = false;
      }
    },

    acceptOffer() {
      if (!this.activeOffer) return;
      const product = {
        id: this.activeOffer.product_id,
        name: this.activeOffer.name,
        price: this.activeOffer.promo_price,
        image: this.activeOffer.image,
        description: "Oferta Especial Adicionada",
        qty: 1,
        modifiers: [],
        is_offer: true,
        offer_source_id: this.activeOffer.offer_id,
        uniqueId: Date.now(),
        station: "default", // Ofertas geralmente não têm estação definida no objeto da oferta
      };
      this.items.push(product);
      notify("success", "Oferta Adicionada!", "Aproveite seu desconto.");
      this.offerSheetOpen = false;
      this.activeOffer = null;
    },

    rejectOffer() {
      if (this.activeOffer) this.rejectedOffers.push(this.activeOffer.offer_id);
      this.offerSheetOpen = false;
      this.activeOffer = null;
    },

    async checkoutWeb() {
      const userStore = useUserStore();
      if (this.items.length === 0) return;
      if (!userStore.isLoggedIn) {
        notify("warn", "Login", "Entre na sua conta para finalizar.");
        return;
      }

      const isPickup = this.checkoutForm.isPickup;
      if (!isPickup && !this.checkoutForm.address) {
        notify("warn", "Endereço", "Selecione um endereço de entrega.");
        return;
      }

      this.isSending = true;
      try {
        let finalPayments = [];
        if (this.checkoutForm.paymentMethod) {
          finalPayments.push({
            method: this.checkoutForm.paymentMethod,
            amount: 0,
            change_for: this.checkoutForm.changeFor || 0,
          });
        }

        let finalAddress = this.checkoutForm.address;
        if (finalAddress && !isPickup) {
          finalAddress = {
            ...finalAddress,
            fee: this.checkoutForm.deliveryFee,
          };
        }

        const payload = {
          items: this.items.map((i) => ({
            id: i.id,
            qty: i.qty,
            modifiers: i.modifiers,
            is_offer: i.is_offer || false,
            offer_source_id: i.offer_source_id || 0,
          })),
          address: isPickup ? null : finalAddress,
          payments: finalPayments,
          coupon: this.checkoutForm.coupon,
          notes: this.checkoutForm.notes,
          source: "app_web_v2",
        };

        const { data } = await api.post("/place-web-order", payload);

        if (data.success) {
          notify("success", "Sucesso!", "Pedido realizado.");

          const webOrder = {
            id: data.order_id,
            type: isPickup ? "pickup" : "delivery",
            table_number: null,
            client_name: userStore.user?.name || "Cliente Web",
            server_name: "App Cliente",
            items: JSON.parse(JSON.stringify(this.items)),
          };

          // 1. Cozinha (Imediato)
          await PrinterService.printKitchen(webOrder);

          // 2. Relatório (Com delay)
          setTimeout(async () => {
            try {
              const details = await api.get(`/order-details/${data.order_id}`);
              if (details.data.success)
                PrinterService.printDeliveryReport(details.data);
            } catch (e) {
              console.error("Erro print web report", e);
            }
          }, 2000);

          this.items = [];
          this.checkoutForm = {
            address: null,
            paymentMethod: null,
            changeFor: 0,
            coupon: "",
            notes: "",
            deliveryFee: 0,
          };
          this.isOpen = false;

          router.push("/home");
        } else {
          throw new Error(data.message);
        }
      } catch (error) {
        notify("error", "Erro", error.response?.data?.message || error.message);
      } finally {
        this.isSending = false;
      }
    },

    async sendOrder() {
      const sessionStore = useSessionStore();
      const userStore = useUserStore();
      const { abbreviateName } = useFormat();

      if (this.items.length === 0) return;

      if (sessionStore.sessionType === "table" && !sessionStore.identifier) {
        notify("warn", "Atenção", "Mesa não identificada.");
        return;
      }

      this.isSending = true;

      try {
        const staffName = userStore.user?.name
          ? abbreviateName(userStore.user.name)
          : "STAFF";

        // Snapshot dos itens para impressão (antes de limpar o carrinho)
        const itemsToSend = JSON.parse(JSON.stringify(this.items));

        // 1. Se não tiver sessão (Balcão rápido), cria agora
        if (!sessionStore.sessionId) {
          const firstItem = itemsToSend[0];
          const payloadFirst = {
            session_id: 0,
            product_id: firstItem.id,
            qty: firstItem.qty,
            modifiers: firstItem.modifiers,
            sub_account: sessionStore.currentAccount,
            table_number: sessionStore.identifier,
            type: sessionStore.sessionType || "counter",
            client_name: sessionStore.clientName || "Cliente",
          };

          const res = await api.post("/add-item", payloadFirst);
          if (res.data.success && res.data.session_id) {
            sessionStore.sessionId = res.data.session_id;
            // Remove o primeiro item, pois já foi enviado na criação
            itemsToSend.shift();
          } else {
            throw new Error("Falha ao criar sessão.");
          }
        }

        // 2. Envia os demais itens
        if (itemsToSend.length > 0) {
          const promises = itemsToSend.map((item) => {
            return api.post("/add-item", {
              session_id: sessionStore.sessionId,
              product_id: item.id,
              qty: item.qty,
              modifiers: item.modifiers,
              sub_account: sessionStore.currentAccount,
            });
          });
          await Promise.all(promises);
        }

        // 3. Atualização de Status/Pagamento (Delivery/Pickup/Counter)
        const isDeliveryOrCounter = ["delivery", "pickup", "counter"].includes(
          sessionStore.sessionType,
        );

        if (isDeliveryOrCounter) {
          if (this.posPaymentPromise.method) {
            const methodObj = this.posPaymentPromise.method;
            const methodType = methodObj.type || methodObj;
            const methodId = methodObj.id || 0;

            const payments = [
              {
                method: methodType,
                method_id: methodId,
                amount: 0,
                change_for: this.posPaymentPromise.changeFor || 0,
              },
            ];

            await api.post("/update-session-meta", {
              session_id: sessionStore.sessionId,
              payment_info: payments,
            });
          }

          const statusToSet = userStore.isStaff ? "preparing" : "new";
          await api.post("/update-order-status", {
            order_id: sessionStore.sessionId,
            status: statusToSet,
          });
        }

        // 4. IMPRESSÃO DA COZINHA (Obrigatória e Imediata)
        // Usa a lista original (this.items) que contém tudo o que foi adicionado AGORA
        // Importante: Passamos os itens com a estação correta (ID) que está na store
        const kitchenOrder = {
          id: sessionStore.sessionId,
          type: sessionStore.sessionType,
          table_number: sessionStore.identifier,
          client_name: sessionStore.clientName || "Cliente",
          server_name: staffName,
          items: JSON.parse(JSON.stringify(this.items)),
        };

        // Envia para a cozinha (Frente/Fundos dependendo do item)
        await PrinterService.printKitchen(kitchenOrder);

        // 5. RELATÓRIO DE CONFERÊNCIA
        if (isDeliveryOrCounter) {
          setTimeout(async () => {
            try {
              const { data } = await api.get(
                `/order-details/${sessionStore.sessionId}`,
              );
              if (data.success) {
                PrinterService.printDeliveryReport(data);
              }
            } catch (e) {
              console.error("Erro ao imprimir relatório de entrega", e);
            }
          }, 2000);
        }

        notify("success", "Pedido Enviado", "Enviado para produção! 🍳");
        await sessionStore.refreshSession();

        // Limpeza
        this.items = [];
        this.posPaymentPromise = { method: null, changeFor: null };
        this.isOpen = false;

        // Redirecionamento
        if (sessionStore.sessionType === "table") {
          router.push("/staff/tables");
        } else if (["delivery", "pickup"].includes(sessionStore.sessionType)) {
          router.push("/staff/delivery");
        } else if (sessionStore.sessionType === "counter") {
          router.push("/staff/pdv");
        }
      } catch (error) {
        console.error(error);
        notify("error", "Erro no Envio", "Não foi possível processar.");
      } finally {
        this.isSending = false;
      }
    },
  },
});
