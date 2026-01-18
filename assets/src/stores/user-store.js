import { defineStore } from "pinia";
import api from "../services/api";
import { isValidCPF, cleanDigits } from "@/utils/validators";
import { notify } from "@/services/notify";

export const useUserStore = defineStore("user", {
  state: () => ({
    user: null,
    isAuthenticated: false,

    // Dados do Onboarding (Formulário)
    onboarding: {
      cpf: "",
      whatsapp: "",
      ddi: "+55",
      birthDate: "",
      fullName: "",
    },

    // UI States
    isLoadingGovApi: false,
    isProfileComplete: false,
  }),

  actions: {
    // Ação Inteligente: Valida -> Verifica Bypass -> Chama API
    async validateAndEnrichCPF() {
      const status = isValidCPF(this.onboarding.cpf);

      if (!status) {
        // Se for inválido e o usuário tiver digitado tudo, avisa
        if (cleanDigits(this.onboarding.cpf).length === 11) {
          notify("warn", "Inválido", "O CPF digitado parece incorreto.");
        }
        return false;
      }

      // MODO TESTE (Economia 💰)
      if (status === "TEST_BYPASS") {
        this.onboarding.fullName = "Cliente Teste Nativa";
        this.onboarding.birthDate = "1990-01-01";
        notify("success", "Modo Teste", "CPF de teste reconhecido.");
        return true;
      }

      // Se chegou aqui, é um CPF válido. Vamos buscar na Receita!
      this.isLoadingGovApi = true;
      try {
        const response = await api.post("/enrich-profile", {
          cpf: cleanDigits(this.onboarding.cpf),
        });

        if (response.data.success) {
          this.onboarding.fullName = response.data.name;
          this.onboarding.birthDate = response.data.birth_date;
          notify(
            "success",
            "Encontrado",
            `Olá, ${this.onboarding.fullName.split(" ")[0]}!`,
          );
          return true;
        } else {
          // API não achou ou deu erro, mas CPF é válido. Deixa digitar manual.
          notify(
            "info",
            "Atenção",
            "Dados não encontrados automaticamente. Por favor, preencha.",
          );
        }
      } catch (error) {
        console.error("Erro na API Gov:", error);
      } finally {
        this.isLoadingGovApi = false;
      }
      return false;
    },

    // Finalizar Cadastro
    async completeRegistration() {
      if (!this.onboarding.fullName || !this.onboarding.whatsapp) {
        notify("warn", "Atenção", "Preencha todos os campos obrigatórios.");
        return;
      }
      // Aqui viria a chamada para salvar o perfil definitivo (/v2/update-profile)
      console.log("Salvando perfil...", this.onboarding);
    },
  },
});
