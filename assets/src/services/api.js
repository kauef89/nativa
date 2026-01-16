import axios from "axios";
import { notify } from "@/services/notify"; // <--- Importe a ponte

const wpData = window.nativaData || {};

const api = axios.create({
  baseURL: wpData.root + "nativa/v2",
  headers: {
    "X-WP-Nonce": wpData.nonce,
    "Content-Type": "application/json",
  },
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const message = error.response?.data?.message || "Erro de comunicação.";

    // Agora usamos o notify elegante do PrimeVue!
    notify("error", "Erro na API", message);

    return Promise.reject(error);
  }
);

export default api;
