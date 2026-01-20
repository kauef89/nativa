<template>
  <div class="flex flex-col h-full w-full bg-surface-950">
    
    <div class="shrink-0 bg-surface-900 border-b border-surface-800 px-6 py-4 flex items-center justify-between shadow-sm z-20">
        <div class="flex items-center gap-6">
            
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl shadow-inner border border-surface-700"
                     :class="sessionStore.sessionType === 'table' ? 'bg-primary-500/20 text-primary-500 border-primary-500/50' : 'bg-surface-800 text-surface-400'">
                    <i class="fa-solid" :class="sessionStore.sessionType === 'table' ? 'fa-table' : 'fa-cash-register'"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white leading-tight">
                        <span v-if="sessionStore.sessionType === 'table'">Mesa {{ sessionStore.identifier }}</span>
                        <span v-else>Balcão / Rápido</span>
                    </h1>
                    <p class="text-[10px] text-surface-400 uppercase font-bold tracking-wider">
                        {{ sessionStore.sessionType === 'table' ? 'Consumo Local' : 'Venda Direta' }}
                    </p>
                </div>
            </div>

            <div class="h-8 w-px bg-surface-800"></div>

            <div class="flex flex-col group cursor-pointer" @click="openAccountsModal">
                <span class="text-[10px] uppercase font-bold text-surface-500 mb-0.5 group-hover:text-primary-400 transition-colors">
                    Lançando em <i class="fa-solid fa-chevron-down ml-1 text-[8px]"></i>
                </span>
                <div class="flex items-center gap-2">
                    <span class="text-lg font-bold text-primary-400 bg-primary-500/10 px-3 py-0.5 rounded border border-primary-500/20 group-hover:bg-primary-500 group-hover:text-white transition-all">
                        <i class="fa-solid fa-user-tag text-xs mr-1.5"></i>
                        {{ sessionStore.currentAccount }}
                    </span>
                    
                    <span v-if="sessionStore.accounts.length > 1" 
                          class="text-xs font-bold text-surface-500 bg-surface-800 px-2 py-1 rounded border border-surface-700">
                        +{{ sessionStore.accounts.length - 1 }}
                    </span>
                </div>
            </div>

        </div>

        <div class="flex gap-2">
             <Button 
                label="Sair" 
                icon="fa-solid fa-arrow-right-from-bracket" 
                class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white hover:!bg-surface-700 transition-colors" 
                @click="emit('exit')" 
             />
        </div>
    </div>

    <div class="flex-1 flex flex-row overflow-hidden gap-4 p-4">
        <div class="flex-1 overflow-hidden relative bg-surface-900 border border-surface-800 rounded-2xl shadow-xl flex flex-col">
           <div class="flex-1 overflow-hidden relative rounded-2xl">
                <product-list @open-product="handleOpenProduct" />
           </div>
        </div>

        <div class="w-96 flex-none bg-surface-900 border border-surface-800 rounded-2xl shadow-xl z-10 h-full flex flex-col overflow-hidden">
           <order-ticket-widget 
                @open-payment="summaryModal.isOpen = true" 
                @exit="emit('exit')" 
           />
        </div>
    </div>

    <cart-drawer @edit-item="handleEditItem" />
    <order-summary-modal ref="summaryModal" />
    <product-details-modal ref="detailsModal" />
    <combo-wizard-modal ref="comboWizard" />

    <table-accounts-modal 
        v-if="sessionStore.sessionType === 'table'"
        v-model:visible="isAccountsModalOpen"
        :session-id="sessionStore.sessionId"
        :table-number="sessionStore.identifier"
    />

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import ProductList from '@/components/ProductList.vue';
import CartDrawer from '@/components/CartDrawer.vue';
import OrderSummaryModal from '@/components/OrderSummaryModal.vue';
import ProductDetailsModal from '@/components/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/ComboWizardModal.vue';
import OrderTicketWidget from '@/components/OrderTicketWidget.vue';
import TableAccountsModal from '@/components/TableAccountsModal.vue'; // <--- Importado
import Button from 'primevue/button';

const sessionStore = useSessionStore();
const cartStore = useCartStore();
const summaryModal = ref(null);
const detailsModal = ref(null);
const comboWizard = ref(null);

// Controle do Modal de Contas
const isAccountsModalOpen = ref(false);

const emit = defineEmits(['exit']);

const openAccountsModal = () => {
    // Só abre se for mesa (Balcão geralmente é conta única)
    if (sessionStore.sessionType === 'table') {
        isAccountsModalOpen.value = true;
    }
};

const handleOpenProduct = (product) => {
  if (product.type === 'combo') {
    comboWizard.value.open(product);
  } else if (product.modifiers && product.modifiers.length > 0) {
    detailsModal.value.open(product);
  } else {
    cartStore.addItem({ ...product, qty: 1, price: parseFloat(product.price), modifiers: [] });
  }
};

const handleEditItem = ({ item, index }) => {
  if (item.type === 'combo') {
    comboWizard.value.open(item, index, item.savedSelections);
  } else if (item.savedSelections) {
    detailsModal.value.open(item, null, index, item.savedSelections);
  }
};
</script>