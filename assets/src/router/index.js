import { createRouter, createWebHistory } from "vue-router";
import { useCashStore } from "../stores/cash-store";
import { useUserStore } from "../stores/user-store";
import { notify } from "@/services/notify";

const SplashView = () => import("../views/SplashView.vue");
const ClientView = () => import("../views/ClientView.vue");
const StaffView = () => import("../views/StaffView.vue");

// Views Internas
const TablesView = () => import("../views/TablesView.vue");
const DeliveryView = () => import("../views/DeliveryView.vue");
const PdvView = () => import("../views/PdvView.vue");
const CounterView = () => import("../views/CounterView.vue");
const CashFlowView = () => import("../views/CashFlowView.vue");
const ProductsView = () => import("../views/ProductsView.vue");
const CustomersView = () => import("../views/CustomersView.vue");
const TeamView = () => import("../views/TeamView.vue");
const SettingsView = () => import("../views/SettingsView.vue");

const router = createRouter({
  history: createWebHistory(),

  routes: [
    {
      path: "/",
      name: "root",
      component: SplashView,
      beforeEnter: (to, from, next) => {
        const userStore = useUserStore();
        const hasSession = userStore.initializeSession();

        if (hasSession) {
          if (userStore.isStaff) {
            // Unificação: Todos os Staff vão para o Mapa de Mesas inicialmente
            next({ name: "staff-tables" });
          } else {
            next({ name: "client-home" });
          }
        } else {
          next();
        }
      },
      meta: { public: true },
    },

    // Área do Cliente
    {
      path: "/home",
      name: "client-home",
      component: ClientView,
      meta: { public: true, layout: "client" },
    },
    {
      path: "/cardapio",
      name: "public-menu",
      component: ClientView,
      meta: { public: true, layout: "client" },
    },

    // Área Staff (Gestão + Operação Unificada)
    {
      path: "/staff",
      component: StaffView,
      meta: { public: false, requiresStaff: true },
      children: [
        { path: "tables", name: "staff-tables", component: TablesView }, // Visão Geral
        { path: "delivery", name: "staff-delivery", component: DeliveryView },
        { path: "pdv", name: "staff-pdv", component: PdvView }, // O PdvView decide se é Mobile ou Desktop
        { path: "counter", name: "staff-counter", component: CounterView },
        { path: "cash-flow", name: "staff-cash-flow", component: CashFlowView },
        { path: "products", name: "staff-products", component: ProductsView },
        {
          path: "customers",
          name: "staff-customers",
          component: CustomersView,
        },
        { path: "team", name: "staff-team", component: TeamView },
        { path: "settings", name: "staff-settings", component: SettingsView },
      ],
    },

    // Redirecionamentos de Legado
    { path: "/tables", redirect: "/staff/tables" },
    { path: "/pdv", redirect: "/staff/pdv" },
    { path: "/delivery", redirect: "/staff/delivery" },
    { path: "/waiter", redirect: "/staff/pdv" }, // Antigo waiter agora vai para o PDV responsivo

    // 404
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: SplashView,
      beforeEnter: (to, from, next) => {
        next({ name: "root" });
      },
    },
  ],
});

// Guards (Mantidos iguais)
router.beforeEach(async (to, from, next) => {
  const cashStore = useCashStore();
  const userStore = useUserStore();

  if (!userStore.isLoggedIn) userStore.initializeSession();

  if (!to.meta.public && !cashStore.statusChecked) {
    try {
      await cashStore.checkStatus();
    } catch (e) {}
  }

  if (to.meta.public) {
    next();
    return;
  }

  if (to.meta.requiresStaff && !userStore.isStaff) {
    if (userStore.isLoggedIn) next({ name: "client-home" });
    else next({ name: "root" });
    return;
  }

  if (to.meta.requiresStaff && !cashStore.isOpen) {
    const allowedClosedRoutes = ["staff-pdv", "staff-settings", "staff-team"];
    if (!allowedClosedRoutes.includes(to.name)) {
      next({ name: "staff-pdv" });
      return;
    }
  }

  next();
});

// --- NOVO: Tratamento de Erro de Atualização (Auto-Reload) ---
router.onError((error, to) => {
  const errors = [
    "Failed to fetch dynamically imported module",
    "Importing a module script failed",
    "Loading chunk",
    "net::ERR_ABORTED",
  ];

  if (errors.some((e) => error.message.includes(e))) {
    console.log("[Router] Detectada versão antiga. Recarregando...");

    // Evita loop infinito: só recarrega se ainda não tiver tentado
    if (!window.location.hash.includes("retry")) {
      window.location.href = to.fullPath; // Força refresh real
    }
  }
});

export default router;
