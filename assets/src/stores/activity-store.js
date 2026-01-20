import { defineStore } from "pinia";

export const useActivityStore = defineStore("activity", {
  state: () => ({
    logs: [],
  }),

  actions: {
    addLog(message, type = "info", meta = {}) {
      this.logs.unshift({
        id: Date.now() + Math.random(),
        message,
        type, // 'order', 'swap', 'payment', 'system'
        time: new Date(),
        meta,
      });
      if (this.logs.length > 50) this.logs.pop();
    },

    getTimeAgo(date) {
      const seconds = Math.floor((new Date() - new Date(date)) / 1000);
      if (seconds < 60) return "agora";
      const minutes = Math.floor(seconds / 60);
      if (minutes < 60) return `${minutes} min`;
      const hours = Math.floor(minutes / 60);
      return `${hours}h`;
    },
  },
});
