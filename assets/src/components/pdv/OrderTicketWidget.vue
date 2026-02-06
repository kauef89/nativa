<template>
  <div class="flex flex-col h-full bg-transparent text-surface-0 relative">
    
    <div class="p-5 border-b border-surface-3/10 flex justify-between items-center shrink-0 z-10">
        <div>
            <h3 class="font-black text-surface-0 text-xs uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-receipt text-primary-500"></i> Resumo
            </h3>
            <div class="text-[10px] text-surface-400 font-bold uppercase tracking-widest mt-0.5">
                Conta: <span class="text-surface-0">{{ sessionStore.currentAccount }}</span>
            </div>
        </div>
        <div class="text-right">
             <span class="block text-[10px] text-surface-500 uppercase font-bold tracking-widest">Total</span>
             <span class="block text-2xl font-black leading-none" :class="total > 0 ? 'text-primary-400' : 'text-surface-500'">
                {{ formatCurrency(total) }}
             </span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin">
        
        <div v-if="activeItems.length > 0" class="space-y-2">
            <div 
                v-for="(item, index) in activeItems" 
                :key="item.id || index"
                class="bg-surface-1 rounded-2xl p-3 flex justify-between group hover:bg-surface-3 transition-all relative pr-2 border border-transparent hover:border-surface-3/50"
            >
                <div class="flex-1 min-w-0 pr-4">
                    <div class="flex items-start gap-3 mb-1">
                        <span class="bg-surface-2 text-surface-300 font-black text-[10px] px-2 py-0.5 rounded-full mt-0.5">
                            {{ item.qty }}x
                        </span>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-200 text-sm leading-tight">{{ item.name }}</span>
                        </div>
                    </div>
                    
                    <div v-if="item.modifiers && item.modifiers.length > 0" class="pl-9 space-y-0.5 mb-1">
                        <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="text-[10px] text-surface-500 flex justify-between font-medium">
                            <span>• {{ mod.name }}</span>
                            <span v-if="mod.price > 0">+{{ formatCurrency(mod.price) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-between items-end pl-2 shrink-0">
                    <div class="font-black text-surface-0 text-sm">{{ formatCurrency(item.line_total) }}</div>
                    
                    <div class="flex gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button class="w-8 h-8 rounded-full bg-surface-2 text-red-400 hover:text-surface-0 hover:bg-red-600 flex items-center justify-center transition-colors" @click.stop="$emit('request-cancel', item)">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="h-full flex flex-col items-center justify-center text-surface-600 opacity-40 py-10 select-none">
            <i class="fa-solid fa-cart-shopping text-5xl mb-4"></i>
            <p class="text-sm font-bold uppercase tracking-wider">Carrinho Vazio</p>
        </div>

    </div>

    <div class="p-5 border-t border-surface-3/10 shrink-0 space-y-3 z-10 shadow-lg">
        
        <div class="grid grid-cols-2 gap-3">
            <Button 
                label="Histórico" 
                icon="fa-solid fa-clock-rotate-left" 
                class="!bg-surface-3 hover:!bg-surface-1 !text-surface-300 hover:!text-surface-0 !font-bold !border-none !rounded-full !h-12" 
                @click="$emit('toggle-history')" 
            />
            
            <Button 
                v-if="total > 0"
                label="Pagar" 
                icon="fa-solid fa-wallet" 
                class="!bg-green-600 hover:!bg-green-500 !border-none text-surface-0 !font-black !rounded-full !h-12"
                @click="$emit('open-payment')"
            />

            <Button 
                v-else
                label="Liberar" 
                icon="fa-solid fa-lock-open" 
                class="!bg-surface-3 !text-red-400 hover:!bg-red-500 hover:!text-surface-0 !font-black !rounded-full !h-12 !border-none"
                @click="handleVoidSession"
            />
        </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useFormat } from '@/composables/useFormat';
import { notify } from '@/services/notify';
import api from '@/services/api';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';
import SessionTimeline from '@/components/shared/SessionTimeline.vue'; // Se necessário, mas aqui só emitimos o evento

const emit = defineEmits(['open-payment', 'toggle-history', 'request-swap', 'request-cancel']);
const sessionStore = useSessionStore();
const router = useRouter();
const { formatCurrency } = useFormat();

const activeItems = computed(() => sessionStore.items.filter(i => i.status !== 'paid' && i.status !== 'cancelled'));

const total = computed(() => sessionStore.totals.total);
const subtotal = computed(() => total.value);

const isItemLocked = (item) => ['swap_pending', 'cancellation_requested'].includes(item.status);

const handleVoidSession = async () => {
    if (!confirm('Deseja LIBERAR esta mesa e encerrar a sessão?')) return;
    try {
        const res = await api.post('/void-session', { session_id: sessionStore.sessionId });
        if (res.data.success) {
            notify('info', 'Mesa Liberada', 'A sessão foi encerrada.');
            sessionStore.leaveSession(); 
            router.push('/staff/tables');      
        }
    } catch (error) {
        notify('error', 'Erro', 'Falha ao liberar mesa.');
    }
};
</script>