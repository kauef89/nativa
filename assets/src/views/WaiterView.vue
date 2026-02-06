{
type: uploaded file
fileName: nativa/src/views/WaiterView.vue
fullContent:
<template>
  <div class="flex flex-col h-full bg-surface-1 w-full overflow-hidden relative rounded-[24px]">
    
    <transition name="slide-down">
        <div v-if="itemToSwap" class="absolute top-24 left-4 right-4 bg-purple-500 text-white p-4 rounded-xl shadow-2xl z-40 flex items-center justify-between animate-pulse-slow border border-white/20 backdrop-blur-md">
            <div class="flex flex-col">
                <span class="text-[10px] font-black uppercase tracking-widest opacity-80">Modo Troca</span>
                <span class="font-bold text-sm">Substituir: <br><strong class="text-lg">{{ itemToSwap.name }}</strong></span>
            </div>
            <Button icon="fa-solid fa-xmark" rounded text class="!text-white hover:!bg-white/20 !w-10 !h-10" @click="cancelSwap" />
        </div>
    </transition>

    <div class="flex-1 overflow-hidden relative">
        <MobileMenu 
            :isWaiterView="true"
            @open-product="handleOpenProduct" 
            @open-accounts="showAccountsModal = true"
            @open-report="openReport"
        />
    </div>

    <CartWidget displayMode="bottom" /> 
    <ProductDetailsModal ref="detailsModal" displayMode="bottom" :show-takeout="true" />
    <ComboWizardModal ref="comboWizard" displayMode="bottom" :show-takeout="true" />
    
    <TableAccountsModal v-model:visible="showAccountsModal" :session-id="sessionStore.sessionId" :table-number="sessionStore.identifier" @account-selected="onAccountSelected" />
    <SwapTableModal v-model:visible="showSwapModal" :source-table="{ number: sessionStore.identifier }" :accounts="sessionStore.accounts" @success="exitTable" />
    
    <SaleFinalizationModal 
        :visible="showFinalizationModal" 
        :order-data="finalizedOrderData" 
        @close="handleFinalizationClose" 
    />

    <TableReportSheet 
        v-model:visible="showReportSheet"
        @payment-success="handlePaymentSuccess"
        @edit-item="handleEditItem"
        @swap-item="handleSwapItem"
        @delete-item="handleDeleteItem"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router'; 
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import { useMenuStore } from '@/stores/menu-store'; 
import api from '@/services/api';
import { notify } from '@/services/notify';

import Button from 'primevue/button';
import MobileMenu from '@/components/shared/MobileMenu.vue';
import ProductDetailsModal from '@/components/shared/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/shared/ComboWizardModal.vue';
import CartWidget from '@/components/shared/CartWidget.vue';
import TableAccountsModal from '@/components/pdv/TableAccountsModal.vue';
import SwapTableModal from '@/components/manager/SwapTableModal.vue';
import SaleFinalizationModal from '@/components/pdv/SaleFinalizationModal.vue';
import TableReportSheet from '@/components/mobile/TableReportSheet.vue';

const router = useRouter();
const route = useRoute();
const sessionStore = useSessionStore();
const cartStore = useCartStore(); 
const menuStore = useMenuStore();

const showReportSheet = ref(false);
const showFinalizationModal = ref(false);
const showAccountsModal = ref(false);
const showSwapModal = ref(false);
const itemToSwap = ref(null);
const finalizedOrderData = ref(null);

const detailsModal = ref(null);
const comboWizard = ref(null);

onMounted(async () => {
    if (!sessionStore.identifier) { router.replace('/staff/tables'); return; }
    if (menuStore.categories.length === 0) await menuStore.fetchMenu();
    await sessionStore.refreshSession();
    
    if (route.query.mode === 'checkout') {
        showReportSheet.value = true;
    }
});

watch(() => route.query.mode, (newMode) => {
    if (newMode === 'checkout') showReportSheet.value = true;
});

watch(showReportSheet, (isOpen) => {
    if (!isOpen && route.query.mode === 'checkout') {
        router.replace({ query: null });
    }
});

const openReport = () => { showReportSheet.value = true; };

const findProductInMenu = (idOrName) => {
    for (const cat of menuStore.categories) {
        if (cat.products) {
            const found = cat.products.find(p => p.id == idOrName || p.name === idOrName);
            if (found) return found;
        }
        if (cat.children) {
            for (const child of cat.children) {
                const found = child.products.find(p => p.id == idOrName || p.name === idOrName);
                if (found) return found;
            }
        }
    }
    return null;
};

const handleSwapItem = (item) => {
    itemToSwap.value = item;
    notify('info', 'Troca', 'Selecione o novo produto no cardápio.');
};

const cancelSwap = () => { itemToSwap.value = null; };

const handleDeleteItem = async (item) => {
    if (!confirm(`Solicitar cancelamento de ${item.qty}x ${item.name}?`)) return;
    try {
        const { data } = await api.post('/cancel-item', { item_id: item.id });
        if (data.success) {
            notify('success', 'Solicitado', data.message || 'Cancelamento enviado.');
            sessionStore.refreshSession();
        }
    } catch(e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao cancelar.');
    }
};

const handleEditItem = async (item) => {
    const product = findProductInMenu(item.product_id || item.name);
    if (!product) { notify('error', 'Erro', 'Produto original não encontrado.'); return; }

    const onSaveEdit = (updatedItem) => {
        const payload = {
            original_item_id: item.id,
            session_id: sessionStore.sessionId,
            new_item: {
                id: product.id,
                name: updatedItem.name,
                qty: updatedItem.qty,
                price: updatedItem.price,
                modifiers: updatedItem.modifiers
            }
        };
        api.post('/swap-items', payload).then((res) => {
            if(res.data.success) {
                notify('success', 'Solicitado', 'Alteração enviada para aprovação.');
                sessionStore.refreshSession();
            }
        }).catch(e => notify('error', 'Erro', 'Falha na edição.'));
    };

    if (product.type === 'combo') {
        notify('warn', 'Aviso', 'Use "Trocar Item" para combos.');
    } else {
        detailsModal.value.open(product, onSaveEdit);
    }
};

const handleOpenProduct = (product) => {
    if (itemToSwap.value) {
        if (!confirm(`Substituir ${itemToSwap.value.name} por ${product.name}?`)) return;
        
        const processSwap = (newItemData) => {
             api.post('/swap-items', { 
                 original_item_id: itemToSwap.value.id, 
                 new_item: newItemData, 
                 session_id: sessionStore.sessionId 
             }).then(() => {
                 notify('success', 'Solicitado', 'Troca enviada para aprovação.');
                 cancelSwap();
                 sessionStore.refreshSession();
             });
        };

        if (product.modifiers?.length > 0 || product.type === 'combo') {
             if (product.type === 'combo') comboWizard.value.open(product, null, null, processSwap);
             else detailsModal.value.open(product, processSwap);
        } else {
             processSwap({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
        }
        return;
    }

    const addToCart = (item) => { 
        cartStore.addItem(item); 
        notify('success', 'Adicionado', `${item.name}`); 
    };

    if (product.type === 'combo') {
        comboWizard.value.open(product);
    } else if (product.modifiers && product.modifiers.length > 0) {
        detailsModal.value.open(product, addToCart);
    } else {
        addToCart({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
    }
};

const handlePaymentSuccess = (data) => {
    finalizedOrderData.value = {
        id: sessionStore.sessionId,
        client_name: sessionStore.currentAccount,
        items: sessionStore.currentAccountItems,
        totals: { total: data.total },
        payments: data.payments
    };
    showReportSheet.value = false;
    showFinalizationModal.value = true;
    sessionStore.refreshSession();
};

const handleFinalizationClose = () => {
    showFinalizationModal.value = false;
    if (sessionStore.sessionStatus === 'closed') exitTable();
};

const onAccountSelected = (acc) => { sessionStore.setAccount(acc); showAccountsModal.value = false; };
const exitTable = () => { 
    if(cartStore.items.length) { if(!confirm('Limpar carrinho e sair?')) return; cartStore.items = []; } 
    sessionStore.leaveSession(); 
    router.push('/staff/tables'); 
};
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-20px); opacity: 0; }
</style>
}