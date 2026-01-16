import { ref } from "vue";

// Referência privada para a instância do Toast do PrimeVue
let toastInstance = null;

// Função chamada pelo App.vue para registrar a instância real
export const registerToast = (instance) => {
  toastInstance = instance;
};

// Função pública que usaremos no lugar do Quasar Notify
export const notify = (type, title, message) => {
  if (!toastInstance) {
    console.warn("Toast não inicializado:", message);
    return;
  }

  // Mapeia os tipos do Quasar/Genéricos para o PrimeVue
  // Quasar: positive, negative, warning, info
  // PrimeVue: success, error, warn, info
  const severityMap = {
    positive: "success",
    negative: "error",
    warning: "warn",
    info: "info",
    success: "success",
    error: "error",
  };

  toastInstance.add({
    severity: severityMap[type] || "info",
    summary: title,
    detail: message,
    life: 3000, // Duração de 3 segundos
  });
};
