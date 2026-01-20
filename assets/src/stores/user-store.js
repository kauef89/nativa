import { defineStore } from "pinia";
import { ref } from "vue";
import api from "../services/api";
import { notify } from "@/services/notify";

export const useUserStore = defineStore("user", () => {
  const user = ref(null);
  const isLoggedIn = ref(false);
  const isLoading = ref(false); // <--- ESTA LINHA ESTAVA FALTANDO

  // Novos Estados para Pedido
  const activeOrder = ref(null);
  const isLoadingOrder = ref(false);

  // Ação para salvar no State E no Cache
  const setUser = (userData) => {
    user.value = userData;
    isLoggedIn.value = true;
    localStorage.setItem("nativa_user", JSON.stringify(userData));
  };

  const initializeSession = async () => {
    const storedUser = localStorage.getItem("nativa_user");
    if (storedUser) {
      try {
        user.value = JSON.parse(storedUser);
        isLoggedIn.value = true;
      } catch (e) {
        console.error("Cache corrompido", e);
        localStorage.removeItem("nativa_user");
      }
    }

    if (isLoggedIn.value || document.cookie.includes("wordpress_logged_in")) {
      await refreshProfile();
    }
  };

  const refreshProfile = async () => {
    try {
      const { data } = await api.get("/auth/me");
      if (data && data.id) {
        const updatedUser = { ...user.value, ...data };
        setUser(updatedUser);
      }
    } catch (e) {
      if (e.response && e.response.status === 401) {
        logout();
      }
    }
  };

  const loginWithGoogle = async (credential) => {
    isLoading.value = true; // Agora funciona!
    try {
      const response = await fetch(
        `${window.nativaData.root}nativa/v2/auth/google`, // Atualizado v2
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": window.nativaData.nonce,
          },
          body: JSON.stringify({ credential }),
        },
      );

      const data = await response.json();

      if (data.success) {
        setUser(data.user);
        if (data.nonce) window.nativaData.nonce = data.nonce;
        return true;
      } else {
        console.error("Login failed:", data);
        return false;
      }
    } catch (error) {
      console.error("Erro no login:", error);
      return false;
    } finally {
      isLoading.value = false;
    }
  };

  const logout = async () => {
    try {
      await fetch(`${window.nativaData.root}nativa/v2/auth/logout`, {
        // Atualizado v2
        method: "POST",
        headers: { "X-WP-Nonce": window.nativaData.nonce },
      });
    } catch (e) {
      console.warn(e);
    }

    user.value = null;
    isLoggedIn.value = false;
    activeOrder.value = null; // Limpa pedido ativo ao sair
    localStorage.removeItem("nativa_user");
    window.location.reload();
  };

  // --- ACTIONS DE PEDIDO ---

  const fetchActiveOrder = async () => {
    if (!isLoggedIn.value) return;
    isLoadingOrder.value = true;
    try {
      const { data } = await api.get("/my-active-order");
      if (data.success) {
        activeOrder.value = data.order;
      }
    } catch (e) {
      console.error("Erro ao buscar pedido ativo", e);
    } finally {
      isLoadingOrder.value = false;
    }
  };

  const cancelActiveOrder = async () => {
    if (!activeOrder.value) return;
    try {
      const { data } = await api.post("/cancel-my-order", {
        order_id: activeOrder.value.id,
      });
      if (data.success) {
        notify("success", "Pedido Cancelado", "Seu pedido foi cancelado.");

        // CORREÇÃO: Não limpa o objeto, apenas muda o status!
        // Isso fará o ClientHome exibir o card vermelho de cancelamento.
        if (activeOrder.value) activeOrder.value.status = "cancelado";
      } else {
        notify("warn", "Não foi possível", data.message);
      }
    } catch (e) {
      notify("error", "Erro", e.response?.data?.message || "Erro ao cancelar.");
    }
  };

  const updatePayment = async (method, changeFor = 0) => {
    if (!activeOrder.value) return;
    try {
      const { data } = await api.post("/update-payment", {
        order_id: activeOrder.value.id,
        method,
        change_for: changeFor,
      });

      if (data.success) {
        notify("success", "Atualizado", "Forma de pagamento alterada.");
        activeOrder.value.payment_method = method;
        return true;
      }
    } catch (e) {
      notify("error", "Erro", e.response?.data?.message || "Erro ao alterar.");
    }
    return false;
  };

  return {
    user,
    isLoggedIn,
    isLoading, // Exportado novamente
    activeOrder,
    isLoadingOrder,
    setUser,
    initializeSession,
    loginWithGoogle,
    refreshProfile,
    logout,
    fetchActiveOrder,
    cancelActiveOrder,
    updatePayment,
  };
});
