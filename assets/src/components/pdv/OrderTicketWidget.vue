<template>
  <div class="flex flex-col h-full bg-surface-900 text-white relative">
    
    <div class="p-4 border-b border-surface-800 bg-surface-950 flex justify-between items-center shrink-0 shadow-sm z-10">
        <div>
            <h3 class="font-bold text-lg text-white">Resumo</h3>
            <div class="text-xs text-primary-400 font-bold uppercase tracking-wider flex items-center gap-1 cursor-pointer hover:text-primary-300 transition-colors">
                <i class="fa-solid fa-user-tag"></i> {{ sessionStore.currentAccount }}
            </div>
        </div>
        <div class="text-right">
             <span class="block text-[10px] text-surface-500 uppercase font-bold tracking-widest">Restante a Pagar</span>
             <span class="block text-xl font-mono font-bold leading-none" :class="total > 0 ? 'text-green-400' : 'text-surface-500'">
                {{ formatMoney(total) }}
             </span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-3 space-y-4 scrollbar-thin">
        
        <div v-if="activeItems.length > 0" class="space-y-2">
            <div 
                v-for="(item, index) in activeItems" 
                :key="item.id || index"
                class="bg-surface-950/50 border border-surface-800 rounded-xl p-3 flex justify-between group hover:border-surface-600 transition-all relative pr-2"
                :class="{'opacity-60 grayscale': isItemLocked(item)}"
            >
                <div class="flex-1 min-w-0 pr-8">
                    <div class="flex items-start gap-3 mb-1">
                        <span class="bg-surface-800 text-surface-300 font-bold text-xs px-1.5 py-0.5 rounded border border-surface-700 font-mono mt-0.5">
                            {{ item.qty }}x
                        </span>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-200 text-sm leading-tight">{{ item.name }}</span>
                            <div v-if="item.status === 'swap_pending'" class="mt-1">
                                <span class="text-[9px] font-bold bg-orange-500/20 text-orange-400 px-1.5 py-0.5 rounded uppercase tracking-wider flex items-center w-fit">
                                    <i class="fa-solid fa-rotate mr-1"></i> Trocando...
                                </span>
                            </div>
                            <div v-else-if="item.status === 'cancellation_requested'" class="mt-1">
                                <span class="text-[9px] font-bold bg-red-500/20 text-red-400 px-1.5 py-0.5 rounded uppercase tracking-wider flex items-center w-fit">
                                    <i class="fa-solid fa-trash-can mr-1"></i> Cancelando...
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="item.modifiers && item.modifiers.length > 0" class="pl-9 space-y-0.5 mb-1">
                        <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="text-[10px] text-surface-500 flex justify-between">
                            <span>• {{ mod.name }}</span>
                            <span v-if="mod.price > 0">+{{ formatMoney(mod.price) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-between items-end pl-2 shrink-0">
                    <div class="font-bold text-surface-300 text-sm font-mono">{{ formatMoney(item.line_total) }}</div>
                    
                    <div class="flex gap-2 mt-2 opacity-0 group-hover:opacity-100 transition-opacity" v-if="!isItemLocked(item)">
                        <button class="w-7 h-7 rounded-lg bg-surface-800 border border-surface-700 text-blue-400 hover:text-white hover:bg-blue-600 flex items-center justify-center transition-colors" @click.stop="$emit('request-swap', item)">
                            <i class="fa-solid fa-arrow-right-arrow-left text-xs"></i>
                        </button>
                        <button class="w-7 h-7 rounded-lg bg-surface-800 border border-surface-700 text-red-400 hover:text-white hover:bg-red-600 flex items-center justify-center transition-colors" @click.stop="$emit('request-cancel', item)">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="sessionStore.paidItems.length === 0" class="h-full flex flex-col items-center justify-center text-surface-700 opacity-40 py-10">
            <i class="fa-solid fa-clipboard-list text-5xl mb-4"></i>
            <p class="text-sm font-medium">Nenhum item lançado.</p>
        </div>

        <div v-if="sessionStore.paidItems.length > 0" class="pt-4 border-t border-surface-800/50">
            <div class="flex items-center gap-2 px-1 mb-3 opacity-40">
                <i class="fa-solid fa-circle-check text-green-500 text-xs"></i>
                <span class="text-[9px] font-black text-surface-400 uppercase tracking-widest">Itens Já Pagos</span>
                <div class="h-px bg-surface-800 flex-1"></div>
            </div>

            <div class="space-y-1.5 opacity-50 grayscale-[40%]">
                <div v-for="item in sessionStore.paidItems" :key="item.id" 
                     class="bg-surface-950/20 border border-surface-800/40 rounded-lg p-2 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-mono text-surface-500">{{ item.qty }}x</span>
                        <span class="text-xs text-surface-400 truncate max-w-[150px]">{{ item.name }}</span>
                    </div>
                    <span class="text-[9px] font-bold text-green-500/70 border border-green-500/20 px-1.5 rounded bg-green-500/5">PAGO</span>
                </div>
            </div>
        </div>

    </div>

    <div class="p-4 border-t border-surface-800 bg-surface-950 shrink-0 space-y-3 z-10 shadow-[0_-5px_20px_rgba(0,0,0,0.3)]">
        
        <div class="flex justify-between text-xs text-surface-500 px-1 font-bold uppercase tracking-wider">
            <span>Subtotal Devedor</span>
            <span class="text-white">{{ formatMoney(subtotal) }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <Button 
                label="Timeline" 
                icon="fa-solid fa-clock-rotate-left" 
                class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white hover:!bg-surface-700 transition-colors w-full" 
                @click="$emit('toggle-history')" 
            />
            
            <Button 
                v-if="total > 0"
                label="Pagamento" 
                icon="fa-solid fa-wallet" 
                class="!bg-green-600 hover:!bg-green-500 !border-none text-white font-bold w-full shadow-lg shadow-green-900/20"
                @click="$emit('open-payment')"
            />

            <Button 
                v-else
                label="Liberar Mesa" 
                icon="fa-solid fa-lock-open" 
                class="!bg-surface-800 !border-surface-700 !text-red-400 hover:!bg-red-500 hover:!text-white font-bold w-full transition-all"
                @click="handleVoidSession"
            />
        </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { notify } from '@/services/notify';
import api from '@/services/api';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';

const emit = defineEmits(['open-payment', 'toggle-history', 'request-swap', 'request-cancel']);
const sessionStore = useSessionStore();
const router = useRouter();

// Itens Ativos (O que falta pagar)
const activeItems = computed(() => sessionStore.items.filter(i => i.status !== 'paid' && i.status !== 'cancelled'));

// Total Devedor
const total = computed(() => sessionStore.totals.total);
const subtotal = computed(() => total.value);

const formatMoney = (val) => 'R$ ' + (parseFloat(val) || 0).toFixed(2).replace('.', ',');

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