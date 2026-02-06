import { createRouter, createWebHistory } from "vue-router";
import { useUserStore } from "../stores/user-store";

// Lazy Load das Views (Melhor performance)
const ClientView = () => import("../views/ClientView.vue");
const StaffView = () => import("../views/StaffView.vue");
const TablesView = () => import("../views/TablesView.vue");
const PdvView = () => import("../views/PdvView.vue");
const DeliveryView = () => import("../views/DeliveryView.vue");
const CounterView = () => import("../views/CounterView.vue");
const ProductsView = () => import("../views/ProductsView.vue");
const CustomersView = () => import("../views/CustomersView.vue");
const TeamView = () => import("../views/TeamView.vue");
const SettingsView = () => import("../views/SettingsView.vue");
const CashFlowView = () => import("../views/CashFlowView.vue");
const OrderDetailsView = () => import("../views/OrderDetailsView.vue");
const LoyaltySettings = () => import("../views/LoyaltySettings.vue");
const OffersView = () => import("../views/OffersView.vue");
const PrintMonitorView = () => import("../views/PrintMonitorView.vue");
const KdsView = () => import("../views/KdsView.vue");
const RestockView = () => import("../views/RestockView.vue");
const PurchasesView = () => import("../views/PurchasesView.vue");
const FiscalMenuView = () => import("../views/FiscalMenuView.vue");

const routes = [
  {
    path: "/",
    // CORREÇÃO: Redirecionamento inteligente baseado no localStorage
    beforeEnter: (to, from, next) => {
      const storedUser = localStorage.getItem("nativa_user");
      if (storedUser) {
        try {
          const user = JSON.parse(storedUser);
          // Se for staff (admin, garçom, gerente), vai pro painel
          if (user.role && user.role !== "customer") {
            next("/staff/tables");
            return;
          }
        } catch (e) {}
      }
      // Se não tiver usuário ou for cliente, vai pra home pública
      next("/home");
    },
  },
  {
    path: "/",
    redirect: "/home", // A lógica de Splash/Home agora é resolvida dentro do ClientView
  },
  {
    path: "/home",
    name: "client-home",
    component: ClientView,
    meta: {
      public: true,
      layout: "client",
    },
  },
  {
    path: "/cardapio",
    name: "public-menu",
    component: ClientView,
    meta: {
      public: true,
      layout: "client",
    },
  },

  // --- ÁREA RESTRITA (STAFF) ---
  {
    path: "/staff",
    component: StaffView,
    meta: {
      requiresAuth: true,
      requiresStaff: true,
    },
    children: [
      {
        path: "",
        redirect: { name: "staff-tables" },
      },

      // --- OPERAÇÃO DE FRENTE ---
      {
        path: "tables",
        name: "staff-tables",
        component: TablesView,
        meta: { permission: "canTables" },
      },
      {
        path: "pdv",
        name: "staff-pdv",
        component: PdvView,
        // Geralmente aberto, mas pode restringir se necessário
      },
      {
        path: "counter",
        name: "staff-counter",
        component: CounterView,
      },
      {
        path: "order/:id",
        name: "staff-order-details",
        component: OrderDetailsView,
      },

      // --- DELIVERY & PRODUÇÃO ---
      {
        path: "delivery",
        name: "staff-delivery",
        component: DeliveryView,
        meta: { permission: "canDelivery" },
      },
      {
        path: "kds",
        name: "staff-kds",
        component: KdsView,
        meta: { permission: "canKitchen" },
      },
      {
        path: "print-monitor",
        name: "staff-print-monitor",
        component: PrintMonitorView,
        meta: { permission: "canPrint" },
      },

      // --- ESTOQUE & COMPRAS ---
      {
        path: "restock",
        name: "staff-restock",
        component: RestockView,
        meta: { permission: "canRestock" },
      },
      {
        path: "purchases",
        name: "staff-purchases",
        component: PurchasesView,
        meta: { permission: "canPurchases" },
      },
      {
        path: "products",
        name: "staff-products",
        component: ProductsView,
        meta: { permission: "canProducts" },
      },

      // --- GESTÃO & CRM ---
      {
        path: "customers",
        name: "staff-customers",
        component: CustomersView,
        meta: { permission: "canCustomers" },
      },
      {
        path: "cash-flow",
        name: "staff-cash",
        component: CashFlowView,
        meta: { permission: "canCash" },
      },

      // --- ADMINISTRAÇÃO ---
      {
        path: "team",
        name: "staff-team",
        component: TeamView,
        meta: { permission: "canTeam" },
      },
      {
        path: "settings",
        name: "staff-settings",
        component: SettingsView,
        meta: { permission: "canSettings" },
      },
      {
        path: "loyalty",
        name: "staff-loyalty",
        component: LoyaltySettings,
        meta: { permission: "canLoyalty" },
      },
      {
        path: "offers",
        name: "staff-offers",
        component: OffersView,
        meta: { permission: "canOffers" },
      },
      {
        path: "fiscal-menu",
        name: "staff-fiscal-menu",
        component: FiscalMenuView,
        meta: { permission: "canFiscal" },
      },

      // Fallback interno para staff
      {
        path: ":pathMatch(.*)*",
        redirect: { name: "staff-tables" },
      },
    ],
  },

  // --- FALLBACK GERAL (404) ---
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    redirect: "/staff/tables",
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// --- GUARDS DE NAVEGAÇÃO ---
router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore();

  // 1. Garante que a sessão foi inicializada (recupera do localStorage/Cookie)
  if (!userStore.isLoggedIn) {
    await userStore.initializeSession();
  }

  // 2. Verifica Autenticação
  if (to.meta.requiresAuth && !userStore.isLoggedIn) {
    next("/");
    return;
  }

  // 3. Verifica se é Staff (Barreira de entrada para a área /staff)
  if (to.meta.requiresStaff && !userStore.isStaff) {
    // Se logado mas não é staff (cliente), manda pra home do cliente
    // Se não logado, manda pro login
    next(userStore.isLoggedIn ? "/home" : "/");
    return;
  }

  // 4. Verifica Permissões Granulares (ACL)
  if (to.meta.permission) {
    const userFlags = userStore.user?.flags || {};
    const hasPermission = userFlags[to.meta.permission] || userFlags.isAdmin;

    if (!hasPermission) {
      // Redireciona para uma rota segura padrão se não tiver acesso
      // Evita loop se 'staff-tables' também for restrito (o que não deve acontecer para staff básico)
      next({ name: "staff-tables" });
      return;
    }
  }

  next();
});

export default router;
