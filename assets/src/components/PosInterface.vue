<template>
  <div class="flex flex-col h-full w-full bg-surface-950">
    
    <div class="h-16 px-6 flex items-center justify-between bg-surface-900 border-b border-surface-800 shadow-md z-20 flex-none">
      <div class="flex items-center">
        <Button icon="pi pi-arrow-left" text rounded class="mr-4 text-surface-400 hover:text-white" @click="emit('exit')" />
        
        <div class="h-8 w-px bg-surface-700 mx-4"></div>
        
        <div class="flex flex-col">
             <div class="flex items-center text-white font-bold text-lg">
                 <i v-if="sessionStore.sessionType === 'table'" class="pi pi-table mr-2 text-primary-500"></i>
                 <i v-else class="pi pi-user mr-2 text-primary-500"></i>
                 {{ sessionStore.sessionLabel }}
             </div>
             <span class="text-xs text-surface-400 font-mono mt-1">PEDIDO #{{ sessionStore.sessionId }}</span>
        </div>
      </div>

      <div class="flex items-center">
         <Button 
            icon="pi pi-shopping-cart" 
            rounded 
            text
            :badge="cartStore.totalItems > 0 ? cartStore.totalItems.toString() : null"
            badge-severity="danger"
            class="text-surface-200 hover:text-white hover:bg-surface-800"
            @click="cartStore.isOpen = !cartStore.isOpen" 
         />
      </div>
    </div>

    <div class="flex-1 flex overflow-hidden">
        
        <div class="flex-1 overflow-y-auto relative bg-surface-950 scrollbar-thin">
           <product-list @open-product="handleOpenProduct" />
        </div>

        <div class="w-96 flex-none bg-surface-900 border-l border-surface-800 shadow-xl z-10 h-full flex flex-col">
           <order-ticket-widget @open-payment="summaryModal.isOpen = true" />
        </div>

    </div>

    <cart-drawer @edit-item="handleEditItem" />
    <order-summary-modal ref="summaryModal" />
    <product-details-modal ref="detailsModal" />
    <combo-wizard-modal ref="comboWizard" />

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import ProductList from '@/components/ProductList.vue';
import CartDrawer from '@/components/CartDrawer.vue'; // Já atualizamos para <Drawer> dentro dele
import OrderSummaryModal from '@/components/OrderSummaryModal.vue';
import ProductDetailsModal from '@/components/ProductDetailsModal.vue';
import ComboWizardModal from '@/components/ComboWizardModal.vue';
import OrderTicketWidget from '@/components/OrderTicketWidget.vue';

// ... (Script mantém igual) ...
const sessionStore = useSessionStore();
const cartStore = useCartStore();
const summaryModal = ref(null);
const detailsModal = ref(null);
const comboWizard = ref(null);

const emit = defineEmits(['exit']);

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