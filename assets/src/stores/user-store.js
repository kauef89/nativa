import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../services/api";
import { notify } from "../services/notify";
import router from "../router";
import { OneSignalService } from "@/services/onesignal";

export const useUserStore = defineStore("user", () => {
  const user = ref(null);
  const isLoggedIn = ref(false);
  const isLoading = ref(false);

  // --- Getters ---
  const isStaff = computed(() => user.value?.flags?.isStaff || false);
  const isAdmin = computed(() => user.value?.flags?.isAdmin || false);

  // --- Actions ---

  // 1. Inicializa
  const initializeSession = () => {
    const storedUser = localStorage.getItem("nativa_user");
    if (storedUser) {
      try {
        user.value = JSON.parse(storedUser);
        isLoggedIn.value = true;
        refreshProfile();
      } catch (e) {
        logout(false);
      }
    }
  };

  // 2. Refresh (Atualiza Nonce e Perfil)
  const refreshProfile = async () => {
    try {
      const res = await api.get("/auth/me");
      if (res.data.success) {
        // [CRÍTICO] Atualiza o Nonce global e do Axios
        if (res.data.nonce) {
          window.nativaData.nonce = res.data.nonce;
          api.defaults.headers.common["X-WP-Nonce"] = res.data.nonce;
        }
        setUserLocal(res.data.user);
        OneSignalService.checkUser();
      }
    } catch (e) {
      if (e.response && e.response.status === 401) {
        logout(false);
      }
    }
  };

  // 3. LOGIN COM GOOGLE
  const loginWithGoogle = async (credential) => {
    isLoading.value = true;
    try {
      // Headers limpos para evitar conflito de nonce antigo no login
      const headers = { "Content-Type": "application/json" };
      if (window.nativaData?.nonce) {
        headers["X-WP-Nonce"] = window.nativaData.nonce;
      }

      // Usa fetch nativo para evitar interceptors sujos do Axios
      const response = await fetch(
        `${window.nativaData.root}nativa/v2/auth/google`,
        {
          method: "POST",
          headers: headers,
          body: JSON.stringify({ credential }),
        },
      );

      const data = await response.json();

      if (data.success) {
        // [CRÍTICO] Injeta o novo Nonce IMEDIATAMENTE no Axios
        if (data.nonce) {
          window.nativaData.nonce = data.nonce;
          api.defaults.headers.common["X-WP-Nonce"] = data.nonce;
        }

        setUserLocal(data.user);
        OneSignalService.checkUser();

        // Feedback e Redirecionamento
        if (isStaff.value) {
          notify(
            "success",
            `Olá, ${data.user.name}`,
            "Painel Staff carregado.",
          );
          router.push("/staff/tables");
        } else {
          notify("success", "Bem-vindo!", "Login realizado com sucesso.");
          if (router.currentRoute.value.path === "/") {
            router.push("/home");
          }
        }
        return true;
      } else {
        throw new Error(data.message || "Falha no login");
      }
    } catch (error) {
      notify("error", "Erro de Login", "Não foi possível autenticar.");
      console.error(error);
      return false;
    } finally {
      isLoading.value = false;
    }
  };

  // 4. LOGOUT (A CORREÇÃO ESTÁ AQUI)
  const logout = async (callApi = true) => {
    try {
      if (callApi) {
        // Tenta avisar o backend (sem bloquear se falhar)
        await api.post("/auth/logout").catch(() => {});
      }

      // Tenta limpar OneSignal
      OneSignalService.logout();

      // Limpa dados locais
      user.value = null;
      isLoggedIn.value = false;
      localStorage.removeItem("nativa_user");

      // Limpa credenciais da memória
      window.nativaData.nonce = "";
      delete api.defaults.headers.common["X-WP-Nonce"];
    } catch (e) {
      console.error("Erro durante logout:", e);
    } finally {
      // [BALA DE PRATA] Força o recarregamento da página.
      // Isso zera a memória do navegador, Socket, OneSignal e Axios.
      // Impede que o "lixo" do usuário anterior afete o próximo.
      window.location.href = "/";
      setTimeout(() => {
        window.location.reload();
      }, 100);
    }
  };

  const setUserLocal = (userData) => {
    user.value = userData;
    isLoggedIn.value = true;
    localStorage.setItem("nativa_user", JSON.stringify(userData));
  };

  return {
    user,
    isLoggedIn,
    isStaff,
    isAdmin,
    isLoading,
    initializeSession,
    refreshProfile,
    loginWithGoogle,
    logout,
  };
});
