import { createRouter, createWebHistory } from "vue-router"; // <--- MUDANÇA 1 (Import)
import { useCashStore } from "../stores/cash-store";

import TablesView from "../views/TablesView.vue";
import CounterView from "../views/CounterView.vue";
import DeliveryView from "../views/DeliveryView.vue";
import PdvView from "../views/PdvView.vue";
import CashFlowView from "../views/CashFlowView.vue";
import ProductsView from "../views/ProductsView.vue";

// Lazy Loading para performance
const SplashView = () => import("../views/SplashView.vue");
const PublicMenuView = () => import("../views/PublicMenuView.vue");

const router = createRouter({
  // MUDANÇA 2: Usar WebHistory em vez de HashHistory
  // Isso remove o "#" da URL e usa a API de Histórico nativa do navegador
  history: createWebHistory(),

  routes: [
    // 1. SPLASH SCREEN (Raiz)
    {
      path: "/",
      name: "splash",
      component: SplashView,
      meta: { public: true, layout: "client" },
    },
    {
      path: "/home",
      name: "client-home",
      component: PublicMenuView, // Reutiliza a View Principal do Cliente
      meta: { public: true, layout: "client" },
    },
    // 2. CARDÁPIO DIGITAL (Cliente)
    {
      path: "/cardapio",
      name: "public-menu",
      component: PublicMenuView,
      meta: { public: true, layout: "client" },
    },

    // 3. PDV / Gateway (Staff)
    {
      path: "/pdv",
      name: "pdv",
      component: PdvView,
      meta: { public: true },
    },

    // --- ROTAS PROTEGIDAS (Abaixo de /pdv ou raiz, conforme necessário) ---
    {
      path: "/tables",
      name: "tables",
      component: TablesView,
    },
    {
      path: "/counter",
      name: "counter",
      component: CounterView,
    },
    {
      path: "/delivery",
      name: "delivery",
      component: DeliveryView,
    },
    {
      path: "/cash-flow",
      name: "cash-flow",
      component: CashFlowView,
    },
    {
      path: "/customers",
      name: "customers",
      component: () => import("../views/CustomersView.vue"),
    },
    {
      path: "/products",
      name: "products",
      component: ProductsView,
    },
    {
      path: "/team",
      name: "team",
      component: () => import("../views/TeamView.vue"),
    },
    {
      path: "/settings",
      name: "settings",
      component: () => import("../views/SettingsView.vue"),
    },
  ],
});

// GUARDS (Proteção de Rotas)
router.beforeEach(async (to, from, next) => {
  const cashStore = useCashStore();

  // Garante que o status do caixa foi verificado
  if (!cashStore.statusChecked) {
    try {
      await cashStore.checkStatus();
    } catch (e) {}
  }

  // Rotas públicas (Splash, Cardápio, Login) passam direto
  if (to.meta.public) {
    next();
    return;
  }

  // Se o caixa estiver FECHADO e tentar acessar rota interna -> Manda para o Login/Aviso
  if (!cashStore.isOpen) {
    if (to.name !== "pdv") next({ name: "pdv" });
    else next();
    return;
  }

  next();
});

export default router;
