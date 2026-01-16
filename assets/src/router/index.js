import { createRouter, createWebHistory } from "vue-router";

// Importação das Views
import TablesView from "@/views/TablesView.vue";
import DeliveryView from "@/views/DeliveryView.vue";
import CounterView from "@/views/CounterView.vue";

// Define a base URL (importante para funcionar em subpastas ou /pdv)
const base = "/pdv/";

const routes = [
  {
    path: "/",
    redirect: "/tables", // Padrão: vai para mesas
  },
  {
    path: "/tables",
    name: "tables",
    component: TablesView,
  },
  {
    path: "/delivery",
    name: "delivery",
    component: DeliveryView,
  },
  {
    path: "/counter",
    name: "counter",
    component: CounterView,
  },
];

const router = createRouter({
  history: createWebHistory(base),
  routes,
});

export default router;
