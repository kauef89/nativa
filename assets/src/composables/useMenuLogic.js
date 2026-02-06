import { ref, computed, onMounted, onUnmounted, nextTick, watch } from "vue";
import { useMenuStore } from "@/stores/menu-store";
import { useUserStore } from "@/stores/user-store";

export function useMenuLogic(propsSearchTerm) {
  const menuStore = useMenuStore();
  const userStore = useUserStore();

  const activeCat = ref("");
  const scrollContainer = ref(null);
  const pillsContainer = ref(null);
  let observer = null;

  // Lógica de Filtro (Texto + Idade + Contexto de Visibilidade + Role)
  const visibleCategories = computed(() => {
    const term = (propsSearchTerm.value || "").toLowerCase().trim();
    const isLoggedIn = userStore.isLoggedIn;
    const isStaff = userStore.isStaff;

    // Define se é Gerente/Admin
    const role = userStore.user?.role;
    const isManager =
      userStore.isAdmin ||
      role === "nativa_manager" ||
      role === "nativa_super_admin";

    // Helper de Idade
    const userAge = (() => {
      if (!isLoggedIn || !userStore.user?.dob) return 0;
      const dob = new Date(userStore.user.dob);
      const diff = Date.now() - dob.getTime();
      return Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
    })();

    const filterProducts = (list) => {
      return list.filter((p) => {
        // 1. Filtro de Texto
        const matchesText =
          !term ||
          p.name.toLowerCase().includes(term) ||
          (p.description && p.description.toLowerCase().includes(term));

        if (!matchesText) return false;

        // 2. Filtro de Visibilidade por Canal
        if (isStaff) {
          // Se for gerente, vê tudo. Se for garçom, respeita a flag.
          if (!isManager && p.show_table === false) return false;
        } else {
          // Visão Cliente (Delivery/App)
          // Clientes NUNCA veem itens ocultos de delivery
          if (p.show_delivery === false) return false;
        }

        // 3. Regra 18+ (Apenas para Clientes)
        if (p.is_18_plus && !isStaff) {
          if (!isLoggedIn || userAge < 18) return false;
        }

        return true;
      });
    };

    return menuStore.categories
      .map((cat) => {
        const catClone = { ...cat };
        catClone.products = filterProducts(catClone.products || []);

        if (catClone.children) {
          catClone.children = catClone.children
            .map((child) => {
              const childClone = { ...child };
              childClone.products = filterProducts(childClone.products || []);
              return childClone.products.length > 0 ? childClone : null;
            })
            .filter(Boolean);
        }

        if (
          catClone.products.length > 0 ||
          (catClone.children && catClone.children.length > 0)
        ) {
          return catClone;
        }
        return null;
      })
      .filter(Boolean);
  });

  // Lógica de ScrollSpy
  const initScrollSpy = () => {
    if (observer) observer.disconnect();
    if (!scrollContainer.value) return;

    const options = {
      root: scrollContainer.value,
      rootMargin: "-15% 0px -60% 0px",
      threshold: 0,
    };

    observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const id = entry.target.id.replace("cat-", "");
          activeCat.value = parseInt(id) || id;

          if (pillsContainer.value) {
            const pill = document.getElementById("pill-" + id);
            if (pill)
              pill.scrollIntoView({
                behavior: "smooth",
                block: "nearest",
                inline: "center",
              });
          }
        }
      });
    }, options);

    nextTick(() => {
      const sections = document.querySelectorAll(".category-section");
      sections.forEach((section) => observer.observe(section));
    });
  };

  const scrollToCategory = (id) => {
    activeCat.value = id;
    const el = document.getElementById("cat-" + id);
    if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  onMounted(async () => {
    if (menuStore.categories.length === 0) await menuStore.fetchMenu();
    setTimeout(initScrollSpy, 500);
  });

  onUnmounted(() => {
    if (observer) observer.disconnect();
  });

  return {
    menuStore,
    visibleCategories,
    activeCat,
    scrollContainer,
    pillsContainer,
    scrollToCategory,
  };
}
