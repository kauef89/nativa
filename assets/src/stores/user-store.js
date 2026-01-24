import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../services/api";
import { notify } from "@/services/notify";

export const useUserStore = defineStore("user", () => {
  const user = ref(null);
  const isLoggedIn = ref(false);
  const isLoading = ref(false);
  const activeOrder = ref(null);
  const isLoadingOrder = ref(false);

  // --- GETTERS BASEADOS EM FLAGS ---

  // Agora verificamos se há flags. Se não houver (versão antiga), fazemos fallback para role.
  const isStaff = computed(() => {
    if (!user.value) return false;
    // Se tiver flags, verifica se tem pelo menos uma permissão de staff
    if (user.value.flags) {
      const f = user.value.flags;
      return (
        f.isAdmin || f.canCash || f.canTables || f.canKitchen || f.canDelivery
      );
    }
    // Fallback antigo
    return [
      "nativa_manager",
      "nativa_waiter",
      "nativa_kitchen",
      "nativa_driver",
      "administrator",
    ].includes(user.value.role);
  });

  const canManageCash = computed(
    () => user.value?.flags?.canCash ?? user.value?.role === "nativa_manager",
  );
  const canManageTeam = computed(
    () => user.value?.flags?.canTeam ?? user.value?.role === "nativa_manager",
  );

  // --- ACTIONS ---

  const setUser = (userData) => {
    user.value = userData;
    isLoggedIn.value = true;
    localStorage.setItem("nativa_user", JSON.stringify(userData));
  };

  const initializeSession = () => {
    const storedUser = localStorage.getItem("nativa_user");
    if (storedUser) {
      try {
        user.value = JSON.parse(storedUser);
        isLoggedIn.value = true;

        // Importante: atualizar perfil para pegar as flags novas
        if (document.cookie.includes("wordpress_logged_in")) {
          refreshProfile();
        }
        return true;
      } catch (e) {
        console.error("Cache corrompido", e);
        localStorage.removeItem("nativa_user");
      }
    }
    return false;
  };

  const refreshProfile = async () => {
    try {
      // Usa o endpoint /auth/me que agora retorna flags
      const res = await api.get("/auth/me");
      // O endpoint retorna o objeto direto, não {data: ...} dependendo da sua config axios,
      // mas no AuthController::get_current_user ele retorna um array direto.
      // O Axios encapsula em .data.

      const userData = res.data; // O endpoint retorna o array direto

      if (userData && userData.id) {
        setUser({ ...user.value, ...userData });
      }
    } catch (e) {
      if (e.response && e.response.status === 401) logout();
    }
  };

  // ... (manter loginWithGoogle, logout, fetchActiveOrder iguais) ...

  const loginWithGoogle = async (credential) => {
    isLoading.value = true;
    try {
      const response = await fetch(
        `${window.nativaData.root}nativa/v2/auth/google`,
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
      }
      return false;
    } catch (error) {
      return false;
    } finally {
      isLoading.value = false;
    }
  };

  const logout = async () => {
    try {
      await fetch(`${window.nativaData.root}nativa/v2/auth/logout`, {
        method: "POST",
        headers: { "X-WP-Nonce": window.nativaData.nonce },
      });
    } catch (e) {}
    user.value = null;
    isLoggedIn.value = false;
    localStorage.removeItem("nativa_user");
    window.location.reload();
  };

  // Funções de pedido mantidas...
  const fetchActiveOrder = async () => {
    // ... (manter código original)
    if (!isLoggedIn.value) return;
    isLoadingOrder.value = true;
    try {
      const { data } = await api.get("/my-active-order");
      if (data.success) activeOrder.value = data.order;
    } catch (e) {
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
        notify("success", "Cancelado", "Pedido cancelado.");
        activeOrder.value.status = "cancelado";
      }
    } catch (e) {
      notify("error", "Erro", e.response?.data?.message);
    }
  };

  const updatePayment = async (method, changeFor = 0) => {
    // ... (manter código original)
    if (!activeOrder.value) return;
    try {
      const { data } = await api.post("/update-payment", {
        order_id: activeOrder.value.id,
        method,
        change_for: changeFor,
      });
      if (data.success) {
        notify("success", "Sucesso", "Pagamento alterado.");
        activeOrder.value.payment_method = method;
        return true;
      }
    } catch (e) {
      notify("error", "Erro", "Falha ao alterar.");
    }
    return false;
  };

  return {
    user,
    isLoggedIn,
    isLoading,
    activeOrder,
    isLoadingOrder,
    isStaff,
    canManageCash,
    canManageTeam, // Exportar novos getters
    setUser,
    initializeSession,
    refreshProfile,
    loginWithGoogle,
    logout,
    fetchActiveOrder,
    cancelActiveOrder,
    updatePayment,
  };
});
