import { computed, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useUserStore } from "@/stores/user-store";
import { useCartStore } from "@/stores/cart-store";
import { useDeliveryStore } from "@/stores/delivery-store";
import { useKdsStore } from "@/stores/kds-store";

export function useNavigation() {
  const router = useRouter();
  const route = useRoute();
  const userStore = useUserStore();
  const cartStore = useCartStore();
  const deliveryStore = useDeliveryStore();
  const kdsStore = useKdsStore();

  const isMenuOpen = ref(false);

  // --- PERMISSÕES ---
  const hasPermission = (perm) => {
    if (!perm) return true;
    return userStore.user?.flags?.[perm] || userStore.user?.flags?.isAdmin;
  };

  const isManager = computed(() => {
    const role = userStore.user?.role;
    return (
      role === "administrator" ||
      role === "nativa_manager" ||
      role === "nativa_super_admin"
    );
  });

  // --- REDIRECIONAMENTO INTELIGENTE (Client -> Staff) ---
  const staffRedirectRoute = computed(() => {
    const role = userStore.user?.role;
    // Cozinha vai direto pro KDS
    if (role === "nativa_kitchen") return "staff-kds";
    // Motoboy vai direto pro Delivery
    if (role === "nativa_driver") return "staff-delivery";
    // Garçom, Gerente e Admin vão para Mesas
    return "staff-tables";
  });

  // --- 1. ITENS DE OPERAÇÃO (Sempre visíveis) ---
  const operationItems = computed(() => {
    const items = [];

    // Mesas
    if (hasPermission("canTables")) {
      items.push({
        key: "tables",
        label: "Mesas",
        icon: "fa-solid fa-utensils",
        route: "staff-tables",
      });
    }

    // Delivery
    if (hasPermission("canDelivery")) {
      items.push({
        key: "delivery",
        label: "Delivery",
        icon: "fa-solid fa-motorcycle",
        route: "staff-delivery",
        badge: deliveryStore.newOrdersCount,
        badgeColor: "bg-orange-500",
      });
    }

    // Balcão (PDV)
    if (hasPermission("canCash") || hasPermission("nativa_access_pos")) {
      items.push({
        key: "pdv",
        label: "Balcão",
        icon: "fa-solid fa-bag-shopping",
        route: "staff-pdv",
      });
    }

    // KDS (Cozinha)
    if (hasPermission("canKitchen")) {
      items.push({
        key: "kds",
        label: "Cozinha", // Label ajustada
        icon: "fa-solid fa-fire-burner",
        route: "staff-kds",
        badge: kdsStore.badgeCount,
        badgeColor: "bg-red-500 animate-pulse",
      });
    }

    return items;
  });

  // --- 2. FERRAMENTAS EXTRAS (Gestão/Admin) ---
  const extraTools = computed(() =>
    [
      {
        label: "Caixa",
        icon: "fa-solid fa-cash-register",
        route: "staff-cash",
        perm: "canCash",
      },
      {
        label: "Produtos",
        icon: "fa-solid fa-burger",
        route: "staff-products",
        perm: "canProducts",
      },
      {
        label: "Compras",
        icon: "fa-solid fa-cart-shopping",
        route: "staff-purchases",
        perm: "canPurchases",
      },
      {
        label: "Reposição",
        icon: "fa-solid fa-boxes-packing",
        route: "staff-restock",
        perm: "canRestock",
      },
      {
        label: "Clientes",
        icon: "fa-solid fa-users",
        route: "staff-customers",
        perm: "canCustomers",
      },
      {
        label: "Ofertas",
        icon: "fa-solid fa-tags",
        route: "staff-offers",
        perm: "canOffers",
      },
      {
        label: "Fidelidade",
        icon: "fa-solid fa-star",
        route: "staff-loyalty",
        perm: "canLoyalty",
      },
      {
        label: "Equipe",
        icon: "fa-solid fa-id-card",
        route: "staff-team",
        perm: "canTeam",
      },
      {
        label: "Config",
        icon: "fa-solid fa-sliders",
        route: "staff-settings",
        perm: "canSettings",
      },
      {
        label: "Impressão",
        icon: "fa-solid fa-print",
        route: "staff-print-monitor",
        perm: "canPrint",
      },
      {
        label: "Fiscal",
        icon: "fa-solid fa-file-invoice-dollar",
        route: "staff-fiscal-menu",
        perm: "canFiscal",
      },
    ].filter((t) => hasPermission(t.perm)),
  );

  // --- 3. CONSTRUÇÃO DA NAVBAR MOBILE ---
  const navItems = computed(() => {
    const isStaffArea = route.path.startsWith("/staff");

    // --> MODO CLIENTE
    if (!isStaffArea) {
      return [
        {
          key: "home",
          label: "Início",
          icon: "fa-solid fa-house",
          route: "client-home",
        },
        {
          key: "menu",
          label: "Cardápio",
          icon: "fa-solid fa-book-open",
          route: "public-menu",
        },
        {
          key: "cart",
          label: "Carrinho",
          icon: "fa-solid fa-cart-shopping",
          action: "openCart",
          badge: cartStore.totalItems,
          badgeColor: "bg-red-500",
        },
        {
          key: "account",
          label: "Perfil",
          icon: "fa-solid fa-user",
          action: "openProfile",
        },
        // Botão de Retorno ao Staff (Se tiver permissão)
        ...(userStore.isStaff
          ? [
              {
                key: "switch",
                label: "Staff",
                icon: "fa-solid fa-rotate",
                action: "switchStaff",
                highlight: true,
              },
            ]
          : []),
      ];
    }

    // --> MODO STAFF
    const items = [...operationItems.value];

    // Se tiver ferramentas extras (Gestão/Admin)
    if (extraTools.value.length > 0) {
      if (isManager.value) {
        // Gerente: Agrupa tudo no botão "Menu" (Gaveta)
        items.push({
          key: "menu_drawer",
          label: "Menu",
          icon: "fa-solid fa-bars",
          action: "toggleMenu",
        });
      } else {
        // Não-Gerente: Mostra ícones soltos na barra (Flatten)
        extraTools.value.forEach((tool) => {
          items.push({
            key: tool.route,
            label: tool.label, // Nome curto pode ser melhor aqui
            icon: tool.icon,
            route: tool.route,
          });
        });
      }
    }

    // Botão Sair/Trocar (Sempre o último)
    items.push({
      key: "switch",
      label: "Loja",
      icon: "fa-solid fa-rotate",
      action: "switchClient",
      highlight: true,
    });

    return items;
  });

  const handleAction = (item, emit) => {
    if (item.route) {
      router.push({ name: item.route });
    } else if (item.action === "openProfile") {
      router.push({ name: "client-home", query: { tab: "profile" } });
    } else if (item.action === "openCart") {
      cartStore.isOpen = !cartStore.isOpen;
    } else if (item.action === "switchClient") {
      router.push("/home");
    } else if (item.action === "switchStaff") {
      // Usa a rota computada baseada no cargo
      router.push({ name: staffRedirectRoute.value });
    } else if (item.action === "toggleMenu") {
      isMenuOpen.value = true;
    } else if (item.action) {
      emit("action", item.action);
    }
  };

  return {
    navItems,
    operationItems,
    extraTools,
    isMenuOpen,
    handleAction,
    isManager,
    hasPermission,
  };
}
