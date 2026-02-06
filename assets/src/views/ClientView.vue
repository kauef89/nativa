<template>
  <div class="flex flex-col h-full w-full bg-surface-base text-surface-200 overflow-hidden relative">
    
    <div class="flex-1 overflow-hidden relative z-10">
        <ClientHome 
            v-if="activeTab === 'home'" 
            @go-to-menu="handleTabChange('menu')" 
        />

        <MobileMenu 
            v-else-if="activeTab === 'menu'" 
            @open-product="openProduct" 
        />

        <ClientProfile 
            v-else-if="activeTab === 'profile'" 
            @open-history="historyDialog.isOpen = true"
            @open-addresses="openAddressModal"
            @open-rewards="rewardsSheet.open()" 
        />
    </div>

    <SharedBottomNav 
        :activeTab="activeTab" 
        @update:activeTab="handleTabChange"
        @trigger="handleNavTrigger"
    />

    <ContactSheet ref="contactSheet" />
    <CartWidget displayMode="bottom" />
    <ProductDetailsModal ref="detailsModal" displayMode="bottom" />
    <ComboWizardModal ref="comboWizard" displayMode="bottom" />
    <OfferSheet />
    <RewardsSheet ref="rewardsSheet" />

    <Dialog 
        v-model:visible="historyDialog.isOpen" 
        modal 
        dismissableMask
        position="bottom"
        :showHeader="false"
        class="!m-0 !rounded-t-[32px] overflow-hidden shadow-[0_-10px_40px_rgba(0,0,0,0.5)]"
        :style="{ width: '100%', maxWidth: '500px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none' }, content: { class: '!p-0' } }"
    >
        <div class="bg-surface-1 h-[85vh] flex flex-col">
            <div class="p-5 border-b border-surface-3/10 text-center relative shrink-0">
                <div class="w-12 h-1.5 bg-surface-3 rounded-full mx-auto mb-4 opacity-50"></div>
                <h3 class="font-black text-surface-0 text-lg">Meus Pedidos</h3>
                <button @click="historyDialog.isOpen = false" class="absolute right-5 top-7 text-surface-400 hover:text-surface-0 transition-colors bg-surface-2 w-8 h-8 rounded-full flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="flex-1 overflow-hidden">
                <OrderHistoryWidget @reorder="handleReorder" />
            </div>
        </div>
    </Dialog>

    <ReorderSheet ref="reorderSheet" />

    <Dialog v-model:visible="addressDialog.isOpen" modal header="Meus Endereços" 
        :style="{ width: '100%', maxWidth: '600px' }" 
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !text-surface-0 !border-b !border-surface-3/10' }, content: { class: '!bg-transparent' } }">
        <div class="text-center p-8">
            <i class="fa-solid fa-map-location-dot text-4xl text-surface-3 mb-4"></i>
            <p class="text-surface-400 font-bold">Gerenciamento de endereços em breve...</p>
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, watch, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart-store';
import { useMenuStore } from '@/stores/menu-store';
import { useUserStore } from '@/stores/user-store';
import { OneSignalService } from '@/services/onesignal'; 
import { notify } from '@/services/notify';

import SharedBottomNav from '@/components/shared/SharedBottomNav.vue';
import ClientHome from '@/components/client/ClientHome.vue';
import MobileMenu from '@/components/shared/MobileMenu.vue';
import ClientProfile from '@/components/client/ClientProfile.vue';
import ContactSheet from '@/components/client/ContactSheet.vue';
import CartWidget from '@/components/shared/CartWidget.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
import RewardsSheet from '@/components/client/RewardsSheet.vue';
import OfferSheet from '@/components/shared/OfferSheet.vue'; 
import ReorderSheet from '@/components/client/ReorderSheet.vue';
import OrderHistoryWidget from '@/components/client/OrderHistoryWidget.vue';
import Dialog from 'primevue/dialog';

const route = useRoute();
const router = useRouter();
const cartStore = useCartStore();
const menuStore = useMenuStore();
const userStore = useUserStore();

const activeTab = ref('home');
const contactSheet = ref(null);
const detailsModal = ref(null);
const comboWizard = ref(null);
const rewardsSheet = ref(null);
const reorderSheet = ref(null);
const addressDialog = reactive({ isOpen: false });
const historyDialog = reactive({ isOpen: false });

const handleTabChange = (newTab) => {
    activeTab.value = newTab;
    if (newTab === 'home') router.replace('/home');
    else if (newTab === 'menu') router.replace('/cardapio');
};

// --- FUNÇÃO COM SONDAS DE DEBUG ---
// --- FUNÇÃO CORRIGIDA COM TIMEOUT ---
const handleNavTrigger = (action) => {
    // Log para depuração imediata
    console.log("🔔 [ClientView] Ação disparada:", action);

    if (action === 'cart') {
        cartStore.isOpen = true;
    }
    else if (action === 'contact') {
        contactSheet.value.open();
    }
    else if (action === 'notifications') {
        // CORREÇÃO CRÍTICA:
        // Removemos o 'await' do nível principal e encapsulamos em uma função async.
        // Isso permite que o Vue libere a UI imediatamente, sem esperar o OneSignal.
        (async () => {
            try {
                notify('info', 'Verificando...', 'Checando status das notificações.');

                // Tenta obter o estado atual com proteção de erro
                let currentState = 'default';
                try {
                    currentState = await OneSignalService.getPermissionState();
                } catch (e) {
                    console.warn("⚠️ [ClientView] Falha ao ler estado, assumindo padrão.", e);
                }

                console.log("🔔 [OneSignal] Estado atual:", currentState);

                if (currentState === 'granted') {
                    notify('success', 'Ativado', 'Você já recebe nossas atualizações! 🔔');
                    return;
                }

                if (currentState === 'denied') {
                    notify('warn', 'Bloqueado', 'Notificações bloqueadas no navegador. Clique no cadeado 🔒 da barra de endereço para liberar.');
                    return;
                }

                // Se chegou aqui, solicita permissão
                const result = await OneSignalService.requestPermission();
                
                if (result === 'granted') {
                    notify('success', 'Sucesso!', 'Agora avisaremos sobre o andamento do seu pedido.');
                } else {
                    notify('info', 'Pendente', 'Precisamos da permissão para avisar sobre a entrega.');
                }

            } catch (err) {
                console.error("🔴 [ClientView] Erro no fluxo de notificação:", err);
                // Falha silenciosa para não assustar o usuário, apenas loga
            }
        })();
    }
};

const setTabFromRoute = () => {
    if (route.query.tab) { activeTab.value = route.query.tab; return; }
    if (route.path.includes('/cardapio')) activeTab.value = 'menu';
    else if (route.path.includes('/home')) activeTab.value = 'home';
};

const openProduct = (product) => {
    if (product.type === 'combo') {
        comboWizard.value.open(product);
    } else if (product.modifiers && product.modifiers.length > 0) {
        detailsModal.value.open(product, (item) => cartStore.addItem(item));
    } else {
        cartStore.addItem({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
    }
};

const handleReorder = (orderId) => {
    historyDialog.isOpen = false;
    reorderSheet.value.open(orderId);
};

const openAddressModal = () => { addressDialog.isOpen = true; };

onMounted(async () => {
    await menuStore.fetchMenu();
    userStore.initializeSession();
    setTabFromRoute();
});

watch(() => route.fullPath, setTabFromRoute);
</script>