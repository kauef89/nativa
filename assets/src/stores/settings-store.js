import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";

export const useSettingsStore = defineStore("settings", {
  state: () => ({
    // Inicializa com estrutura válida para evitar "undefined property"
    hours: {
      general: {},
      delivery: {},
      pickup: {},
    },
    status: { general: false, delivery: false, pickup: false },
    contact: {
      whatsapp: "",
      address: "",
      instagram: "",
      google_reviews: "",
    },
    isLoading: false,
  }),

  actions: {
    async fetchAllSettings() {
      this.isLoading = true;
      try {
        const resHours = await api.get("/settings/hours");
        if (resHours.data.success) this.hours = resHours.data.hours;

        const resGeneral = await api.get("/settings/general");
        if (resGeneral.data.success && resGeneral.data.contact) {
          this.contact = { ...this.contact, ...resGeneral.data.contact };
        }
      } catch (e) {
        console.error(e);
      } finally {
        this.isLoading = false;
      }
    },

    async fetchPublicSettings() {
      try {
        const { data } = await api.get("/settings/public");
        if (data.success) {
          if (data.contact) this.contact = data.contact;
          // Garante a mesclagem correta dos horários
          if (data.hours) {
            this.hours = { ...this.hours, ...data.hours };
          }
        }
      } catch (e) {
        console.log("Usando configurações padrão.");
      }
    },

    async saveAll() {
      try {
        await api.post("/settings/hours", { hours: this.hours });
        await api.post("/settings/general", { contact: this.contact });

        notify("success", "Sucesso", "Configurações atualizadas!");
        await this.checkRealtimeStatus();
        return true;
      } catch (e) {
        notify("error", "Erro", "Falha ao salvar.");
      }
      return false;
    },

    async checkRealtimeStatus() {
      try {
        const { data } = await api.get("/store-status");
        this.status = data;
      } catch (e) {
        console.error(e);
      }
    },
  },
});
