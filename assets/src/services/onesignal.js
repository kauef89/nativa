import api from "./api";

// Controle local para garantir singleton
let isInitialized = false;
let isCheckingUser = false;

export const OneSignalService = {
  async init() {
    // Evita execução no SSR ou se já iniciou
    if (typeof window === "undefined" || isInitialized) return;

    window.OneSignal = window.OneSignal || [];
    const config = window.nativaOneSignal || {};

    if (!config.app_id) {
      console.warn("⚠️ [OneSignal] App ID não configurado.");
      return;
    }

    // Marca como iniciado para bloquear chamadas duplas imediatas
    isInitialized = true;

    // Atraso seguro (Lazy Load) para não competir com a renderização do Vue
    setTimeout(() => {
      try {
        console.log("🚀 [OneSignal] Iniciando serviço...");

        window.OneSignal.push(async function () {
          // Se já estiver inicializado pelo script nativo, aborta
          if (window.OneSignal.initialized) {
            console.log("✅ [OneSignal] Já estava inicializado.");
            return;
          }

          // Inicialização Mínima (Sem Slidedown, Sem Listeners Manuais)
          await window.OneSignal.init({
            appId: config.app_id,
            safari_web_id: config.safari_id,
            allowLocalhostAsSecureOrigin: true,
            // Bloqueia prompts automáticos para evitar conflito de DOM
            promptOptions: {
              slidedown: { prompts: [] },
            },
          });

          console.log("✅ [OneSignal] Serviço pronto.");

          // REMOVIDO: O listener "foregroundWillDisplay" que causava o erro.
          // O comportamento padrão já é exibir.

          // Verifica usuário após estabilização
          OneSignalService.checkUser();
        });
      } catch (error) {
        console.warn("🔴 [OneSignal] Falha silenciosa no init:", error);
      }
    }, 2000); // 2 segundos é suficiente se removermos o listener problemático
  },

  async setUserId(userId, hash) {
    if (!userId) return;

    window.OneSignal.push(async function () {
      // Safe check
      if (!window.OneSignal.User) return;

      try {
        const currentId = window.OneSignal.User.externalId;
        if (String(currentId) === String(userId)) return;

        if (hash) {
          await window.OneSignal.login(String(userId), hash);
        } else {
          await window.OneSignal.login(String(userId));
        }
      } catch (e) {
        // Silencia erros de login para não travar app
      }
    });
  },

  logout() {
    if (typeof window === "undefined") return;
    window.OneSignal.push(() => {
      try {
        if (window.OneSignal.User) window.OneSignal.logout();
      } catch (e) {}
    });
  },

  async sendTags(tags) {
    if (typeof window === "undefined") return;
    window.OneSignal.push(() => {
      try {
        if (window.OneSignal.User) window.OneSignal.User.addTags(tags);
      } catch (e) {}
    });
  },

  async checkUser() {
    if (isCheckingUser) return;
    isCheckingUser = true;

    // Roda em background
    setTimeout(async () => {
      try {
        const storedUser = localStorage.getItem("nativa_user");
        if (storedUser) {
          const user = JSON.parse(storedUser);
          if (user && user.id) {
            await OneSignalService.setUserId(user.id, user.onesignal_hash);

            const role = user.role || "customer";
            OneSignalService.sendTags({
              role: role,
              is_staff: role !== "customer" ? "true" : "false",
            });

            // Sincroniza Token (Player ID)
            window.OneSignal.push(() => {
              try {
                // Verificação ultra-defensiva antes de acessar PushSubscription
                if (
                  window.OneSignal.User &&
                  window.OneSignal.User.PushSubscription &&
                  typeof window.OneSignal.User.PushSubscription.id !==
                    "undefined"
                ) {
                  const subId = window.OneSignal.User.PushSubscription.id;
                  if (subId) {
                    api
                      .post("/my-profile/device-token", { token: subId })
                      .catch(() => {});
                  }

                  // Listener de mudança de token (apenas se suportado)
                  if (
                    window.OneSignal.User.PushSubscription.addEventListener &&
                    typeof window.OneSignal.User.PushSubscription
                      .addEventListener === "function"
                  ) {
                    window.OneSignal.User.PushSubscription.addEventListener(
                      "change",
                      (event) => {
                        if (event.current && event.current.id) {
                          api
                            .post("/my-profile/device-token", {
                              token: event.current.id,
                            })
                            .catch(() => {});
                        }
                      },
                    );
                  }
                }
              } catch (subErr) {
                // Ignora falhas de subscrição
              }
            });
          }
        }
      } catch (e) {
        console.error("🔴 [OneSignal] Erro no checkUser:", e);
      } finally {
        isCheckingUser = false;
      }
    }, 1000);
  },

  // Método seguro para checar permissão (sem acessar propriedades profundas)
  async getPermissionState() {
    if (typeof window === "undefined") return "default";

    // Tenta usar a API nativa primeiro (mais segura e rápida)
    if (window.Notification) {
      return window.Notification.permission;
    }

    // Fallback para OneSignal se necessário
    try {
      if (window.OneSignal && window.OneSignal.Notifications) {
        return window.OneSignal.Notifications.permission ? "granted" : "denied";
      }
    } catch (e) {}

    return "default";
  },

  async requestPermission() {
    return new Promise((resolve) => {
      window.OneSignal.push(async () => {
        try {
          // Prioriza API do OneSignal dentro do contexto seguro do push
          if (window.OneSignal.Notifications) {
            await window.OneSignal.Notifications.requestPermission();
          } else if (window.Notification) {
            await window.Notification.requestPermission();
          }
        } catch (e) {
          console.warn("⚠️ Falha ao pedir permissão:", e);
        }

        // Retorna o estado final
        const finalState = window.Notification
          ? window.Notification.permission
          : "default";
        resolve(finalState);
      });
    });
  },
};
