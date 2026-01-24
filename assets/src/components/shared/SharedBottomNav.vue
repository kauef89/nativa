<template>
  <div class="fixed bottom-6 left-1/2 -translate-x-1/2 w-auto max-w-[95vw] z-50">
    
    <transition name="slide-up-fade">
        <div v-if="showManagementMenu" class="absolute bottom-[84px] left-0 w-full bg-surface-900/95 backdrop-blur-xl border border-surface-700 rounded-2xl shadow-2xl p-2 flex flex-col gap-1 overflow-hidden origin-bottom">
            <div class="px-4 py-2 text-[10px] uppercase font-bold text-surface-500 tracking-widest border-b border-surface-800 mb-1">
                Gestão & Configurações
            </div>
            
            <div class="grid grid-cols-4 gap-2 p-2">
                <button 
                    v-for="item in managementItems" 
                    :key="item.id"
                    class="flex flex-col items-center justify-center gap-2 p-3 rounded-xl hover:bg-surface-800 transition-colors text-surface-400 hover:text-white"
                    @click="handleAction(item)"
                >
                    <div class="w-10 h-10 rounded-full bg-surface-800 flex items-center justify-center text-lg border border-surface-700">
                        <i :class="item.icon"></i>
                    </div>
                    <span class="text-[9px] font-bold text-center leading-tight">{{ item.shortLabel || item.label }}</span>
                </button>
            </div>
        </div>
    </transition>

    <div v-if="displayItems.length === 0" class="bg-red-500 text-white p-2 rounded text-center text-xs font-bold">
        ⚠️ DEBUG: Menu Vazio
    </div>

    <div v-else class="bg-surface-900/90 backdrop-blur-md border border-surface-800 rounded-full shadow-2xl h-[64px] relative flex items-center px-2 gap-1">
        
        <div 
            class="absolute h-[54px] bg-white rounded-full shadow-lg transition-all duration-300 ease-[cubic-bezier(0.16, 1, 0.3, 1)] z-0"
            :style="activeIndicatorStyle"
        ></div>

        <button 
            v-for="(item, index) in displayItems" 
            :key="item.id"
            class="relative w-[54px] h-[54px] flex items-center justify-center z-10 transition-colors duration-200 group focus:outline-none tap-highlight-transparent"
            @click="handleNavClick(item)"
        >
            <div class="relative flex items-center justify-center w-full h-full">
                <i class="text-xl transition-transform duration-300" 
                   :class="[
                       item.icon,
                       isActive(item) ? 'text-surface-950 scale-110' : 'text-surface-400 group-hover:text-surface-200 group-active:scale-90'
                   ]"
                ></i>

                <span v-if="item.id === 'cart' && cartCount > 0" 
                      class="absolute top-2 right-2 bg-red-600 text-white text-[9px] font-bold h-4 min-w-[16px] px-1 rounded-full flex items-center justify-center border border-surface-900 shadow-sm animate-bounce">
                    {{ cartCount }}
                </span>
            </div>
        </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useUserStore } from '@/stores/user-store';
import { useCartStore } from '@/stores/cart-store';

const props = defineProps(['activeTab']); 
const emit = defineEmits(['update:activeTab', 'trigger']);

const router = useRouter();
const route = useRoute();
const userStore = useUserStore();
const cartStore = useCartStore();

const showManagementMenu = ref(false);

const baseClientItems = [
    { id: 'home', icon: 'fa-solid fa-house', route: '/home' },
    { id: 'contact', icon: 'fa-brands fa-whatsapp', action: 'contact' },
    { id: 'menu', icon: 'fa-solid fa-utensils', route: '/cardapio' },
    { id: 'profile', icon: 'fa-solid fa-user', route: '/home?tab=profile' },
    { id: 'cart', icon: 'fa-solid fa-cart-shopping', action: 'cart' }
];

const operationalItems = [
    { id: 'tables', label: 'Mesas', icon: 'fa-solid fa-map', route: '/staff/tables', permission: 'canTables' },
    { id: 'delivery', label: 'Delivery', icon: 'fa-solid fa-motorcycle', route: '/staff/delivery', permission: 'canDelivery' },
];

const backofficeItems = [
    { id: 'products', label: 'Produtos', shortLabel: 'Estoque', icon: 'fa-solid fa-boxes-stacked', route: '/staff/products', permission: 'canProducts' },
    { id: 'cash-flow', label: 'Financeiro', shortLabel: 'Caixa', icon: 'fa-solid fa-sack-dollar', route: '/staff/cash-flow', permission: 'canCash' },
    { id: 'customers', label: 'Clientes', shortLabel: 'CRM', icon: 'fa-solid fa-users', route: '/staff/customers', permission: 'canCustomers' },
    { id: 'team', label: 'Equipe', shortLabel: 'Time', icon: 'fa-solid fa-id-badge', route: '/staff/team', permission: 'canTeam' },
    { id: 'settings', label: 'Configurações', shortLabel: 'Config', icon: 'fa-solid fa-cog', route: '/staff/settings', permission: 'canSettings' }
];

const isStaffContext = computed(() => route.path.includes('/staff') || route.name === 'waiter-mode');

const hasAccess = (item) => {
    const flags = userStore.user?.flags || {};
    if (flags.isAdmin) return true;
    if (item.permission && flags[item.permission]) return true;
    return false;
};

const displayItems = computed(() => {
    if (!isStaffContext.value) {
        const items = [...baseClientItems];
        if (userStore.isStaff) items.push({ id: 'staff-mode', icon: 'fa-solid fa-arrows-rotate', action: 'switch-to-staff' });
        return items;
    }
    const items = operationalItems.filter(item => hasAccess(item));
    const allowedTools = backofficeItems.filter(item => hasAccess(item));
    if (allowedTools.length > 0) items.push({ id: 'tools', icon: 'fa-solid fa-screwdriver-wrench', action: 'toggle-menu' });
    items.push({ id: 'client-mode', icon: 'fa-solid fa-arrows-rotate', action: 'switch-to-client' });
    return items;
});

const managementItems = computed(() => isStaffContext.value ? backofficeItems.filter(item => hasAccess(item)) : []);
const cartCount = computed(() => cartStore.totalItems);

/**
 * Lógica de Ativação Refinada
 * Se o menu de ferramentas está aberto, APENAS o ícone 'tools' é considerado ativo.
 */
const isActive = (item) => {
    if (showManagementMenu.value) {
        return item.id === 'tools';
    }
    if (props.activeTab === item.id) return true;
    if (item.route && route.path.startsWith(item.route)) {
        if (item.id === 'home' && route.path !== '/home') return false;
        return true;
    }
    return false;
};

/**
 * Cálculo do Indicador
 * Usa pixels fixos (58px por item) para garantir o movimento exato do círculo.
 */
const activeIndicatorStyle = computed(() => {
    const items = displayItems.value;
    const isMenuOpen = showManagementMenu.value; // Força reatividade quando o menu abre

    const index = items.findIndex(item => isActive(item));
    if (index === -1) return { opacity: 0 };

    const itemWidth = 58; // 54px largura + 4px (gap-1)
    const offset = 8;    // px-2 padding

    return { 
        width: '54px', 
        left: `${(index * itemWidth) + offset}px`, 
        opacity: 1 
    };
});

const handleNavClick = (item) => {
    if (item.action === 'toggle-menu') {
        showManagementMenu.value = !showManagementMenu.value;
    } else {
        showManagementMenu.value = false;
        handleAction(item);
    }
};

const handleAction = (item) => {
    if (item.action === 'switch-to-client') {
        router.push('/home');
    } else if (item.action === 'switch-to-staff') {
        const flags = userStore.user?.flags || {};
        if (userStore.user?.role === 'nativa_waiter') router.push({ name: 'waiter-mode' }); 
        else if (flags.canTables) router.push('/staff/tables');
        else router.push('/staff/pdv'); 
    } else if (item.action === 'cart' || item.action === 'contact') {
        emit('trigger', item.action);
    } else if (item.route) {
        if (['profile', 'home', 'menu'].includes(item.id)) emit('update:activeTab', item.id);
        router.push(item.route);
    }
};
</script>

<style scoped>
.tap-highlight-transparent { -webkit-tap-highlight-color: transparent; }
.slide-up-fade-enter-active, .slide-up-fade-leave-active { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
.slide-up-fade-enter-from, .slide-up-fade-leave-to { transform: translateY(20px) scale(0.95); opacity: 0; }
</style>