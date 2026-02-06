import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";
import { PrinterService } from "@/services/printer-service";
import notificationSound from "@/sounds/notification.mp3";
import alarmSoundFile from "@/sounds/alarm.mp3";

export const useKdsStore = defineStore("kds", {
  state: () => ({
    orders: [],
    cozinhas: [],
    selectedCozinhaId: localStorage.getItem("nativa_kds_cozinha_id") || null,
    isLoading: false,
    autoRefreshInterval: null,
    lastOrderCount: 0,
    finishedItemIds: new Set(
      JSON.parse(localStorage.getItem("nativa_kds_finished_items") || "[]"),
    ),
    audioNotification: new Audio(notificationSound),
    audioAlarm: new Audio(alarmSoundFile),
    timers: {},
    timerInterval: null,
    activeAlarm: null,
    now: Date.now(),
  }),

  getters: {
    columns: (state) => {
      const currentTime = state.now;
      const allItems = [];

      state.orders.forEach((order) => {
        let stationItems = (order.items || []).filter((i) => {
          // Usa '==' para permitir string (localStorage) vs number (API)
          const isMatch = i.station_id == state.selectedCozinhaId;
          return isMatch && i.status !== "cancelled";
        });

        stationItems = stationItems.map((i) => {
          let computedStatus = i.status;

          // Verifica se está marcado como finalizado localmente ou no backend
          if (state.finishedItemIds.has(i.id)) {
            computedStatus = "finished";
          } else if (i.status === "finished") {
            computedStatus = "finished";
          } else {
            // CORREÇÃO: Grab&Go agora nasce como 'pending' para aparecer em 'A Fazer'
            computedStatus = "pending";
          }
          return { ...i, status: computedStatus };
        });

        if (stationItems.length > 0) {
          let dateString = (order.created_at || order.date || "").toString();
          if (/^\d{2}\/\d{2}\/\d{4}/.test(dateString)) {
            const [d, t] = dateString.split(" ");
            const [day, month, year] = d.split("/");
            dateString = `${year}-${month}-${day}T${t || "00:00"}:00`;
          } else if (dateString.includes(" ") && !dateString.includes("T")) {
            dateString = dateString.replace(" ", "T");
          }

          const createdTime = new Date(dateString).getTime();
          const validCreatedTime = isNaN(createdTime)
            ? currentTime
            : createdTime;
          const waitMinutes = Math.floor(
            Math.max(0, currentTime - validCreatedTime) / 60000,
          );

          // Um pedido está "Todo Pronto" apenas se todos os itens desta estação estiverem 'finished'
          const allFinished = stationItems.every(
            (i) => i.status === "finished",
          );

          allItems.push({
            ...order,
            displayItems: stationItems,
            allFinished: allFinished,
            waitMinutes: waitMinutes,
          });
        }
      });

      return {
        todo: allItems.filter(
          (o) =>
            ["new", "pending", "open", "pending_payment"].includes(
              o.status_slug || o.status,
            ) && !o.allFinished,
        ),
        doing: allItems.filter(
          (o) =>
            ["preparing", "preparando"].includes(o.status_slug || o.status) &&
            !o.allFinished,
        ),
        done: allItems.filter(
          (o) =>
            ["ready", "pronto"].includes(o.status_slug || o.status) ||
            o.allFinished,
        ),
      };
    },

    badgeCount(state) {
      if (!state.selectedCozinhaId) return 0;
      return this.columns ? this.columns.todo.length : 0;
    },
  },

  actions: {
    async fetchCozinhas() {
      try {
        const { data } = await api.get("/logistica/cozinhas");
        if (data.success) {
          this.cozinhas = data.cozinhas;
          if (!this.selectedCozinhaId && data.cozinhas.length > 0) {
            this.setCozinha(data.cozinhas[0].id);
          }
        }
      } catch (e) {
        notify("error", "Erro", "Falha ao carregar estações de cozinha.");
      }
    },

    setCozinha(id) {
      this.selectedCozinhaId = id;
      localStorage.setItem("nativa_kds_cozinha_id", id);
      this.fetchQueue();
    },

    async fetchQueue() {
      try {
        const { data } = await api.get("/orders");
        if (data.success) {
          const activeOrders = data.orders.filter(
            (o) =>
              !["finished", "cancelled", "concluido", "cancelado"].includes(
                o.status_slug,
              ),
          );

          if (
            activeOrders.length > this.lastOrderCount &&
            this.lastOrderCount !== 0
          ) {
            this.playNotification();
          }
          this.lastOrderCount = activeOrders.length;

          const detailedOrders = await Promise.all(
            activeOrders.map(async (o) => {
              try {
                const res = await api.get(`/order-details/${o.id}`);
                const items = res.data.items.map((i) => ({
                  ...i,
                  status: this.finishedItemIds.has(i.id)
                    ? "finished"
                    : i.status || "pending",
                  requires_prep: i.requires_prep,
                }));

                return res.data.success
                  ? {
                      ...o,
                      items,
                      created_at: res.data.date,
                      server_name:
                        res.data.server_name || o.server_name || "Garçom",
                    }
                  : null;
              } catch {
                return null;
              }
            }),
          );
          this.orders = detailedOrders.filter(Boolean);
        }
      } catch (e) {
        console.error("Erro KDS:", e);
      }
    },

    playNotification() {
      this.audioNotification.play().catch(() => {});
    },

    startTimer(order, item, minutes) {
      const durationSec = minutes * 60;
      const now = Date.now();
      const label =
        order.modality === "delivery"
          ? `Delivery ${order.client.split(" ")[0]}`
          : order.modality === "pickup"
            ? `Retirada ${order.client.split(" ")[0]}`
            : order.client || `Pedido #${order.id}`;
      this.timers[item.id] = {
        end: now + durationSec * 1000,
        total: durationSec,
        remaining: durationSec,
        percent: 100,
        itemId: item.id,
        itemName: item.name,
        orderId: order.id,
        orderLabel: label,
        running: true,
      };
      if (!this.timerInterval) {
        this.timerInterval = setInterval(() => this.tickTimers(), 1000);
      }
    },
    stopTimer(itemId) {
      if (this.timers[itemId]) delete this.timers[itemId];
    },
    tickTimers() {
      const now = Date.now();
      this.now = now;
      const ids = Object.keys(this.timers);
      ids.forEach((id) => {
        const t = this.timers[id];
        const left = Math.ceil((t.end - now) / 1000);
        if (left <= 0) {
          this.triggerAlarm(t);
          delete this.timers[id];
        } else {
          t.remaining = left;
          t.percent = (left / t.total) * 100;
        }
      });
    },
    triggerAlarm(timerData) {
      this.activeAlarm = timerData;
      this.audioAlarm.loop = true;
      this.audioAlarm.currentTime = 0;
      this.audioAlarm.play().catch(() => {});
    },
    silenceAlarm() {
      this.activeAlarm = null;
      this.audioAlarm.pause();
    },

    async toggleItemStatus(orderId, itemId) {
      const orderIdx = this.orders.findIndex((o) => o.id === orderId);
      if (orderIdx === -1) return;

      const item = this.orders[orderIdx].items.find((i) => i.id === itemId);
      if (item) {
        if (item.status === "finished") {
          item.status = "pending";
          this.finishedItemIds.delete(itemId);
        } else {
          item.status = "finished";
          this.finishedItemIds.add(itemId);
        }
        localStorage.setItem(
          "nativa_kds_finished_items",
          JSON.stringify([...this.finishedItemIds]),
        );
      }
    },

    async printSingleItem(order, item) {
      try {
        const payload = {
          id: order.id,
          type: order.modality === "Entrega" ? "delivery" : "table",
          table_number: order.source === "pos" ? order.client : null,
          client_name: order.client,
          items: [item],
          targetStation: this.selectedCozinhaId,
        };
        await PrinterService.printKitchen(payload);
        notify("success", "Impresso", `${item.name} enviado.`);
      } catch (e) {
        notify("error", "Erro", "Falha na impressão.");
      }
    },

    async updateStatus(order, newStatus, successMsg) {
      const orderIdx = this.orders.findIndex((o) => o.id === order.id);
      if (orderIdx > -1) {
        this.orders[orderIdx].status = newStatus;
        this.orders[orderIdx].status_slug = newStatus;
      }
      try {
        await api.post("/update-order-status", {
          order_id: order.id,
          status: newStatus,
        });
        if (successMsg) notify("success", "Atualizado", successMsg);
      } catch (e) {
        notify("error", "Erro", "Falha ao atualizar.");
      }
    },

    async startOrder(order) {
      await this.updateStatus(order, "preparing", null);
    },
    async readyOrder(order) {
      await this.updateStatus(order, "ready", `Pedido #${order.id} Pronto!`);
    },
    async finishOrder(order) {
      await this.updateStatus(order, "finished", null);
      this.orders = this.orders.filter((o) => o.id !== order.id);
    },
    async reprintTicket(order) {
      try {
        const payload = {
          id: order.id,
          type: order.modality === "Entrega" ? "delivery" : "table",
          table_number: order.source === "pos" ? order.client : null,
          client_name: order.client,
          items: order.displayItems,
          targetStation: this.selectedCozinhaId,
        };
        await PrinterService.printKitchen(payload);
        notify("info", "Enviado", "Reimpressão completa.");
      } catch (e) {}
    },

    startPolling() {
      this.fetchQueue();
      if (this.autoRefreshInterval) clearInterval(this.autoRefreshInterval);
      this.autoRefreshInterval = setInterval(() => this.fetchQueue(), 10000);
      if (!this.timerInterval) {
        this.timerInterval = setInterval(() => this.tickTimers(), 1000);
      }
    },
    stopPolling() {
      if (this.autoRefreshInterval) clearInterval(this.autoRefreshInterval);
    },
  },
});
