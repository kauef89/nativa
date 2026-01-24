import axios from "axios";
import { notify } from "@/services/notify";

const wpData = window.nativaData || {};

const api = axios.create({
  baseURL: wpData.root + "nativa/v2",
  headers: {
    "Content-Type": "application/json",
  },
});

// 1. INTERCEPTADOR DE REQUISIÇÃO (Novo)
// Injeta o Nonce atualizado a cada requisição
api.interceptors.request.use(
  (config) => {
    if (window.nativaData && window.nativaData.nonce) {
      config.headers["X-WP-Nonce"] = window.nativaData.nonce;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// 2. INTERCEPTADOR DE RESPOSTA (Existente)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Tratamento para 403 (Nonce inválido ou Sessão expirada)
    if (error.response && error.response.status === 403) {
      console.warn("Erro 403: Verificando nonce ou permissão.");
      // Opcional: Se for erro de nonce ("cookie_nonce_invalid"), poderia tentar renovar ou deslogar
    }

    const message = error.response?.data?.message || "Erro de comunicação.";

    // Evita spam de notificação para erros "silenciosos" (opcional)
    if (error.config && !error.config.silent) {
      notify("error", "Erro na API", message);
    }

    return Promise.reject(error);
  },
);

export default api;
