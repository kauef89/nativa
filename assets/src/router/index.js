// src/router/index.js

// 1. Mude a importação de createWebHistory para createWebHashHistory
import { createRouter, createWebHashHistory } from "vue-router";

// ... importação dos componentes continua igual ...
import TablesView from "../views/TablesView.vue";
import CounterView from "../views/CounterView.vue";
import DeliveryView from "../views/DeliveryView.vue";
import OnboardingView from "../views/OnboardingView.vue";

const router = createRouter({
  // 2. Use o Hash History aqui. Não precisa mais do import.meta.env.BASE_URL
  history: createWebHashHistory(),

  routes: [
    {
      path: "/",
      redirect: "/tables",
    },
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
      path: "/onboarding",
      name: "onboarding",
      component: OnboardingView,
    },
    {
      path: "/customers",
      name: "customers",
      component: () => import("../views/CustomersView.vue"), // Lazy load
    },
  ],
});

export default router;
