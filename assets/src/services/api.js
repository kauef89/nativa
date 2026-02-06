import axios from "axios";

// Cria a instância básica
const api = axios.create({
  baseURL: window.nativaData?.root + "nativa/v2",
  headers: {
    "Content-Type": "application/json",
  },
});

// Interceptor de Requisição: Injeta o Nonce
api.interceptors.request.use(
  (config) => {
    const currentNonce = window.nativaData?.nonce;
    if (currentNonce) {
      config.headers["X-WP-Nonce"] = currentNonce;
    }
    return config;
  },
  (error) => Promise.reject(error),
);

// Interceptor de Resposta: AUTO-RECOVERY (Cura o erro 403)
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    // Se for erro 403 de Nonce e ainda não tentamos recuperar
    if (
      error.response &&
      error.response.status === 403 &&
      error.response.data?.code === "rest_cookie_invalid_nonce" &&
      !originalRequest._retry
    ) {
      originalRequest._retry = true; // Marca para não entrar em loop infinito

      try {
        console.log("🔄 [API] Nonce expirado. Renovando...");

        // Pede um nonce novo usando uma instância limpa do axios (sem interceptors)
        const { data } = await axios.get(
          window.nativaData.root + "nativa/v2/auth/nonce",
        );

        if (data.success && data.nonce) {
          // Atualiza globalmente
          window.nativaData.nonce = data.nonce;
          api.defaults.headers.common["X-WP-Nonce"] = data.nonce;

          // Atualiza a requisição que falhou e tenta de novo
          originalRequest.headers["X-WP-Nonce"] = data.nonce;
          return api(originalRequest);
        }
      } catch (refreshError) {
        console.error("🔴 [API] Falha crítica ao renovar nonce.", refreshError);
        // Se falhar a renovação, redireciona para login (sessão morreu de vez)
        window.location.href = "/";
      }
    }

    return Promise.reject(error);
  },
);

export default api;
