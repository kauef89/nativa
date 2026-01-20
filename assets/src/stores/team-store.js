import { defineStore } from "pinia";
import api from "@/services/api";
import { notify } from "@/services/notify";

export const useTeamStore = defineStore("team", {
  state: () => ({
    members: [],
    isLoading: false,
  }),

  actions: {
    async fetchTeam() {
      this.isLoading = true;
      try {
        const { data } = await api.get("/team");
        if (data.success) this.members = data.team;
      } catch (e) {
        console.error(e);
      } finally {
        this.isLoading = false;
      }
    },

    async saveMember(memberData) {
      try {
        const { data } = await api.post("/team", memberData);
        if (data.success) {
          notify("success", "Sucesso", "Colaborador salvo.");
          await this.fetchTeam();
          return true;
        }
      } catch (e) {
        notify(
          "error",
          "Erro",
          e.response?.data?.message || "Falha ao salvar.",
        );
        return false;
      }
    },
  },
});
