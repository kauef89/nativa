<template>
  <div class="flex flex-col h-full bg-surface-900 w-full">
    
    <div class="p-5 bg-surface-800 border-b border-surface-700 flex items-center justify-between shadow-sm flex-none">
      <div class="font-bold text-surface-200 flex items-center text-lg">
        <i class="pi pi-receipt mr-3 text-primary-500"></i>
        Mesa {{ sessionStore.currentTable }}
      </div>
      <span class="px-2 py-1 bg-surface-950 border border-surface-700 rounded text-xs text-surface-400 font-mono">
        #{{ sessionStore.sessionId }}
      </span>
    </div>

    <div class="flex-1 overflow-y-auto p-0 relative scrollbar-thin">
      
      <div v-if="sessionStore.isLoading" class="flex items-center justify-center h-full">
        <ProgressSpinner style="width: 40px; height: 40px" />
      </div>

      <div v-else-if="!sessionStore.orderItems.length" class="flex flex-col items-center justify-center h-full text-surface-500">
        <i class="pi pi-shopping-bag text-5xl mb-4 opacity-20"></i>
        <span class="text-sm">Nenhum pedido lançado.</span>
      </div>

      <ul v-else class="list-none p-0 m-0 divide-y divide-surface-800">
        <li v-for="(item, index) in sessionStore.orderItems" :key="index" 
            class="p-4 hover:bg-surface-800/50 transition-colors flex items-start group"
        >
            <div class="flex-none font-bold text-surface-900 bg-primary-500 rounded-md w-8 h-8 flex items-center justify-center text-sm mr-4 shadow-sm">
                {{ item.qty }}
            </div>

            <div class="flex-1 min-w-0">
                <div class="font-medium text-surface-200 truncate" :class="{'line-through text-surface-500': item.status === 'cancelled'}">
                    {{ item.name }}
                </div>
                
                <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 space-y-0.5">
                    <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="text-xs text-surface-400 flex items-center">
                        <i class="pi pi-angle-right text-[10px] mr-1 opacity-50"></i> {{ mod.name }}
                    </div>
                </div>

                <div v-if="item.status === 'cancelled'" class="text-xs text-red-400 font-bold mt-1 uppercase tracking-wider">Cancelado</div>
            </div>

            <div class="flex flex-col items-end ml-3 flex-none">
                <span class="font-bold text-sm text-surface-300" :class="item.status === 'cancelled' ? 'line-through text-surface-500' : ''">
                    {{ formatMoney(item.line_total) }}
                </span>
                
                <button 
                    v-if="item.status !== 'cancelled'"
                    class="mt-2 text-surface-500 hover:text-red-400 transition-colors p-1 opacity-0 group-hover:opacity-100"
                    @click="confirmCancel(item)"
                >
                    <i class="pi pi-trash"></i>
                </button>
            </div>
        </li>
      </ul>
    </div>

    <div class="p-5 bg-surface-900 border-t border-surface-800 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.3)] z-20 flex-none">
      <div class="flex justify-between items-end mb-6">
        <span class="text-sm text-surface-400 font-bold uppercase tracking-wider">Total</span>
        <span class="text-3xl font-bold text-primary-500">R$ {{ formatMoney(sessionStore.orderTotal) }}</span>
      </div>

      <div class="grid grid-cols-4 gap-3">
        <div class="col-span-1">
            <Button icon="pi pi-refresh" outlined severity="secondary" class="w-full h-12 !border-surface-700 !text-surface-400" @click="sessionStore.fetchOrderSummary" />
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
import api from '@/services/api';

const sessionStore = useSessionStore();
const emit = defineEmits(['open-payment']);

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