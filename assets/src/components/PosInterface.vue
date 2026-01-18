<template>
  <div class="flex flex-col h-full w-full">
    
    <div class="flex-1 flex flex-row overflow-hidden gap-3">
        
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