<template>
  <Drawer 
    v-model:visible="store.isOpen" 
    position="right" 
    :modal="true"
    :pt="{
        root: { class: '!bg-surface-900 !border-l !border-surface-800 w-full md:w-[450px]' },
        header: { class: '!bg-surface-900 !border-b !border-surface-800 !px-6 !py-4' },
        content: { class: '!bg-surface-900 !p-0' },
        closeButton: { class: '!text-surface-300 hover:!text-white hover:!bg-surface-800' },
        mask: { class: '!bg-black/50 backdrop-blur-sm' } 
    }"
  >
    <template #header>
        <div class="flex items-center font-bold text-xl text-white">
            <i class="pi pi-shopping-cart mr-3 text-primary-500"></i> Carrinho
        </div>
    </template>

    <div class="flex flex-col h-full bg-surface-900">
        
        <div class="flex-1 overflow-y-auto scrollbar-thin p-2">
            <div v-if="store.items.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-400">
                <i class="pi pi-shopping-cart text-6xl mb-4 opacity-20"></i>
                <span>Seu carrinho está vazio</span>
            </div>

            <ul v-else class="space-y-1">
                <li v-for="(item, index) in store.items" :key="item.uniqueId" 
                    class="p-4 rounded-xl hover:bg-surface-800 cursor-pointer transition-colors group border border-transparent hover:border-surface-700"
                    @click="emit('edit-item', { item, index })"
                >
                    <div class="flex items-start">
                        <div class="w-14 h-14 bg-surface-800 rounded-lg flex items-center justify-center mr-4 overflow-hidden flex-none border border-surface-700">
                            <img v-if="item.image" :src="item.image" class="w-full h-full object-cover">
                            <i v-else class="pi pi-box text-2xl text-surface-500"></i>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-surface-100 text-lg mb-1">{{ item.name }}</div>
                            <div v-if="item.modifiers && item.modifiers.length" class="text-sm text-surface-300 mb-2">
                                <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx">
                                    • {{ mod.name }} <span v-if="mod.price > 0" class="text-primary-500 font-bold ml-1">+{{ formatMoney(mod.price) }}</span>
                                </div>
                            </div>
                            <div class="text-primary-500 font-bold">R$ {{ formatMoney(item.price * item.qty) }}</div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-3 pt-3 border-t border-surface-800 group-hover:border-surface-700">
                        <div class="flex items-center bg-surface-950 rounded-lg border border-surface-800 p-1">
                            <Button icon="pi pi-minus" text rounded class="!w-8 !h-8 !text-surface-300 hover:!text-white hover:!bg-surface-800" @click.stop="store.decreaseQty(index)" />
                            <span class="font-bold w-8 text-center text-white">{{ item.qty }}</span>
                            <Button icon="pi pi-plus" text rounded class="!w-8 !h-8 !text-surface-300 hover:!text-white hover:!bg-surface-800" @click.stop="store.increaseQty(index)" />
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="bg-surface-900 border-t border-surface-800 p-6 pb-8">
            <div class="flex justify-between items-end mb-6">
                <span class="text-lg text-surface-300">Total do Pedido</span>
                <span class="text-3xl font-bold text-primary-500">R$ {{ formatMoney(store.totalValue) }}</span>
            </div>

            <Button 
                label="ENVIAR PARA COZINHA" 
                icon="pi pi-send" 
                class="w-full h-14 font-bold text-lg !bg-primary-500 hover:!bg-primary-400 !border-none !text-surface-900 shadow-lg shadow-primary-500/20" 
                :loading="store.isSending"
                :disabled="store.items.length === 0"
                @click="checkAndSend"
            />
        </div>
    </div>
  </Drawer>
</template>

<script setup>
import { useCartStore } from '@/stores/cart-store';
import { useSessionStore } from '@/stores/session-store';
import api from '@/services/api';
import Drawer from 'primevue/drawer';

const store = useCartStore();
const sessionStore = useSessionStore();
const emit = defineEmits(['edit-item']);

const formatMoney = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return '0,00';
  return num.toFixed(2).replace('.', ',');
};

const checkAndSend = async () => {
  if (sessionStore.sessionType === 'counter' && (!sessionStore.clientName || sessionStore.clientName === '')) {
      const newName = prompt("Nome do Cliente ou Senha:");
      if (newName) {
          try {
             await api.post('/update-session', { session_id: sessionStore.sessionId, client_name: newName });
             sessionStore.clientName = newName;
             store.sendOrder();
          } catch (e) { alert('Erro ao salvar nome.'); }
      }
  } else {
    store.sendOrder();
  }
};
</script>