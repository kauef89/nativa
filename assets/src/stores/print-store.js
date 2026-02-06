import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";

export const usePrintStore = defineStore("print", {
  state: () => ({
    logs: [],
    isLoading: false,
  }),
  actions: {
    async fetchLogs() {
      this.isLoading = true;
      try {
        const { data } = await api.get("/print/logs");
        if (data.success) this.logs = data.logs;
      } finally {
        this.isLoading = false;
      }
    },

    async reprint(log) {
      try {
        await api.post("/print/add", {
          type: log.type,
          station: log.station,
          data: JSON.parse(log.payload),
        });
        notify("success", "Reimpressão", "Comando enviado novamente.");
        this.fetchLogs();
      } catch (e) {
        notify("error", "Falha", "Não foi possível reenviar.");
      }
    },
  },
});
