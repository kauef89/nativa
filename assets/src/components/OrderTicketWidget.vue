<template>
  <div class="flex flex-col h-full w-full bg-surface-900"> <div class="p-5 bg-surface-800 border-b border-surface-700 flex items-center justify-between shadow-sm flex-none h-20 rounded-t-2xl">
      
      <div class="flex flex-col">
         <div class="flex items-center text-white font-bold text-xl">
             <i v-if="sessionStore.sessionType === 'table'" class="pi pi-table mr-2 text-primary-500"></i>
             <i v-else class="pi pi-user mr-2 text-primary-500"></i>
             {{ sessionStore.sessionLabel }}
         </div>
         <span class="text-xs text-surface-400 font-mono mt-1">
            PEDIDO #{{ sessionStore.sessionId }}
         </span>
      </div>

      <Button 
        icon="pi pi-shopping-cart" 
        text rounded 
        class="!w-12 !h-12 !text-surface-300 hover:!text-white hover:!bg-surface-700 relative"
        @click="cartStore.isOpen = !cartStore.isOpen"
        v-tooltip.left="'Ver Carrinho'"
      >
         <Badge v-if="cartStore.totalItems > 0" :value="cartStore.totalItems" severity="danger" class="absolute top-1 right-1" />
      </Button>

    </div>

    <div class="flex-1 overflow-y-auto p-0 relative scrollbar-thin">
      
      <div v-if="sessionStore.isLoading" class="flex items-center justify-center h-full">
        <ProgressSpinner style="width: 40px; height: 40px" />
      </div>

      <div v-else-if="!sessionStore.orderItems.length" class="flex flex-col items-center justify-center h-full text-surface-500 p-8 text-center">
        <i class="pi pi-receipt text-6xl mb-4 opacity-20"></i>
        <span class="text-sm">Nenhum pedido lançado.</span>
        <span class="text-xs text-surface-600 mt-2">Adicione itens para começar.</span>
      </div>

      <ul v-else class="list-none p-0 m-0 divide-y divide-surface-800">
        <li v-for="(item, index) in sessionStore.orderItems" :key="index" 
            class="p-4 hover:bg-surface-800/50 transition-colors flex items-start group"
        >
            <div class="flex-none font-bold text-surface-900 bg-primary-500 rounded-md w-7 h-7 flex items-center justify-center text-xs mr-3 shadow-sm mt-0.5">
                {{ item.qty }}
            </div>

            <div class="flex-1 min-w-0">
                <div class="font-medium text-surface-200 text-sm leading-tight" :class="{'line-through text-surface-500': item.status === 'cancelled'}">
                    {{ item.name }}
                </div>
                
                <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 space-y-0.5">
                    <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="text-[11px] text-surface-400 flex items-center">
                        <i class="pi pi-angle-right text-[9px] mr-1 opacity-50"></i> {{ mod.name }}
                    </div>
                </div>

                <div v-if="item.status === 'cancelled'" class="text-[10px] text-red-400 font-bold mt-1 uppercase tracking-wider">Cancelado</div>
            </div>

            <div class="flex flex-col items-end ml-2 flex-none">
                <span class="font-bold text-sm text-surface-300" :class="item.status === 'cancelled' ? 'line-through text-surface-500' : ''">
                    {{ formatMoney(item.line_total) }}
                </span>
                
                <button 
                    v-if="item.status !== 'cancelled'"
                    class="mt-1 text-surface-500 hover:text-red-400 transition-colors p-1 opacity-0 group-hover:opacity-100"
                    @click="confirmCancel(item)"
                    title="Cancelar Item"
                >
                    <i class="pi pi-trash text-sm"></i>
                </button>
            </div>
        </li>
      </ul>
    </div>

    <div class="p-5 bg-surface-900 border-t border-surface-800 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.3)] z-20 flex-none rounded-b-2xl">
      <div class="flex justify-between items-end mb-6">
        <span class="text-sm text-surface-400 font-bold uppercase tracking-wider">Total</span>
        <span class="text-3xl font-bold text-primary-500">R$ {{ formatMoney(sessionStore.orderTotal) }}</span>
      </div>

      <div class="grid grid-cols-4 gap-3">
        <div class="col-span-1">
            <Button icon="pi pi-refresh" outlined severity="secondary" class="w-full h-12 !border-surface-700 !text-surface-400" @click="sessionStore.fetchOrderSummary" v-tooltip.top="'Atualizar'" />
        </div>
        <div class="col-span-3">
            <Button 
                label="PAGAR" 
                icon="pi pi-wallet" 
                class="w-full h-12 !font-bold !bg-primary-500 hover:!bg-primary-400 !border-none !text-surface-900 shadow-lg shadow-primary-500/20"
                :disabled="sessionStore.orderTotal <= 0"
                @click="emit('open-payment')"
            />
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { useSessionStore } from '@/stores/session-store';
import { useCartStore } from '@/stores/cart-store';
import api from '@/services/api';
import Badge from 'primevue/badge';

const sessionStore = useSessionStore();
const cartStore = useCartStore();
const emit = defineEmits(['open-payment', 'exit']);

const formatMoney = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return '0,00';
  return num.toFixed(2).replace('.', ',');
};

const confirmCancel = async (item) => {
    if (confirm(`Deseja cancelar ${item.name}?`)) {
        try {
            await api.post('/cancel-item', { item_id: item.id });
            sessionStore.fetchOrderSummary();
        } catch (error) {
            console.error('Erro cancelar');
        }
    }
};
</script>