import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";

export const useSettingsStore = defineStore("settings", {
  state: () => ({
    hours: null,
    status: { general: false, delivery: false, pickup: false },
    // DADOS DE CONTATO (O que estava faltando)
    contact: {
      whatsapp: "",
      address: "",
      instagram: "",
      google_reviews: "", // <--- NOVO
    },
    isLoading: false,
  }),

  actions: {
    // 1. Busca TUDO (Horários + Geral) para a tela de Admin
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

    // 2. Busca APENAS dados públicos (Para o ClientHome)
    // OBS: Renomeei para 'fetchPublicSettings' para ficar claro
    async fetchPublicSettings() {
      try {
        const { data } = await api.get("/settings/public");
        if (data.success) {
          this.contact = data.contact;
        }
      } catch (e) {
        console.log("Usando configurações padrão de contato");
      }
    },

    // 3. Salva TUDO
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
