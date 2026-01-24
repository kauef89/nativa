<template>
  <div class="flex flex-col h-full w-full bg-surface-950 text-surface-200 overflow-hidden relative">
    
    <div class="flex-1 overflow-hidden relative">
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
            @open-history="openHistoryModal"
            @open-addresses="openAddressModal"
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

    <Dialog v-model:visible="addressDialog.isOpen" modal header="Meus Endereços" :style="{ width: '100%', maxWidth: '600px' }" :pt="{ root: { class: '!bg-surface-900 !border-surface-700' } }">
        <div class="text-center p-4">
            <p>Gerenciamento de endereços em breve...</p>
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

// Componentes Importados (O código foi movido para cá)
import SharedBottomNav from '@/components/shared/SharedBottomNav.vue';
import ClientHome from '@/components/client/ClientHome.vue';
import MobileMenu from '@/components/shared/MobileMenu.vue'; // <--- O Cardápio está aqui dentro!
import ClientProfile from '@/components/client/ClientProfile.vue';
import ContactSheet from '@/components/client/ContactSheet.vue';
import CartWidget from '@/components/shared/CartWidget.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
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
const addressDialog = reactive({ isOpen: false });

const handleTabChange = (newTab) => {
    activeTab.value = newTab;
    if (newTab === 'home') router.replace('/home');
    else if (newTab === 'menu') router.replace('/cardapio');
};

const handleNavTrigger = (action) => {
    if (action === 'cart') cartStore.isOpen = true;
    else if (action === 'contact') contactSheet.value.open();
};

const setTabFromRoute = () => {
    if (route.path.includes('/cardapio')) activeTab.value = 'menu';
    else if (route.path.includes('/home')) activeTab.value = 'home';
};

const openProduct = (product) => {
    if (product.type === 'combo') {
        comboWizard.value.open(product);
    } else if (product.modifiers && product.modifiers.length > 0) {
        detailsModal.value.open(product, (item) => cartStore.addItem(item));
    } else {
        cartStore.addItem({ 
            ...product, 
            qty: 1, 
            price: parseFloat(product.price), 
            modifiers: [] 
        });
    }
};

// Funções placeholder para o perfil
const openHistoryModal = () => {};
const openAddressModal = () => { addressDialog.isOpen = true; };

onMounted(async () => {
    await menuStore.fetchMenu();
    userStore.initializeSession();
    setTabFromRoute();
});

watch(() => route.path, setTabFromRoute);
</script>