<template>
  <div class="flex flex-col h-full bg-surface-900 text-white relative">
    
    <div class="p-4 border-b border-surface-800 bg-surface-950 flex justify-between items-center shrink-0">
        <div>
            <h3 class="font-bold text-lg text-white">Resumo do Pedido</h3>
            <div class="text-xs text-primary-400 font-bold uppercase tracking-wider flex items-center gap-1">
                <i class="fa-solid fa-user-tag"></i> {{ sessionStore.currentAccount }}
            </div>
        </div>
        <div class="text-right">
             <span class="block text-xs text-surface-400">Total</span>
             <span class="block text-xl font-mono font-bold" :class="total > 0 ? 'text-green-400' : 'text-surface-400'">
                {{ formatMoney(total) }}
             </span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-3 space-y-2 scrollbar-thin">
        
        <div v-if="!activeItems || activeItems.length === 0" class="h-full flex flex-col items-center justify-center text-surface-600 opacity-50">
            <i class="fa-solid fa-chair text-6xl mb-4"></i>
            <p class="text-sm font-medium">Nenhum item ativo.</p>
        </div>

        <div v-else class="space-y-2">
            <div 
                v-for="(item, index) in activeItems" 
                :key="item.id || index"
                class="bg-surface-800/50 border border-surface-700 rounded-lg p-3 flex justify-between group hover:border-surface-600 transition-colors"
            >
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="bg-surface-700 text-white font-bold text-xs px-1.5 py-0.5 rounded">{{ item.qty }}x</span>
                        <span class="font-bold text-surface-200 text-sm">{{ item.name }}</span>
                    </div>
                    
                    <div v-if="item.modifiers && item.modifiers.length > 0" class="pl-7 space-y-0.5">
                        <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="text-xs text-surface-400 flex justify-between">
                            <span>• {{ mod.name }}</span>
                            <span v-if="mod.price > 0">+{{ formatMoney(mod.price) }}</span>
                        </div>
                    </div>

                    <div class="pl-7 mt-1.5 flex items-center gap-2">
                         <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded"
                               :class="item.status === 'pending' ? 'bg-yellow-500/20 text-yellow-500' : 'bg-green-500/20 text-green-500'">
                            {{ item.status === 'pending' ? 'Pendente' : 'Confirmado' }}
                         </span>
                         <button 
                            class="text-[10px] text-red-500 hover:text-red-400 hover:underline opacity-0 group-hover:opacity-100 transition-opacity"
                            @click="handleCancelItem(item)"
                         >
                            Cancelar
                         </button>
                    </div>
                </div>

                <div class="text-right">
                    <div class="font-bold text-surface-300 text-sm">{{ formatMoney(item.line_total) }}</div>
                </div>
            </div>
        </div>

    </div>

    <div class="p-4 border-t border-surface-800 bg-surface-950 shrink-0 space-y-3">
        
        <div class="space-y-1 mb-2">
            <div class="flex justify-between text-xs text-surface-400">
                <span>Subtotal</span>
                <span>{{ formatMoney(subtotal) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <Button 
                label="Voltar" 
                icon="fa-solid fa-arrow-left" 
                class="!bg-surface-800 !border-surface-700 !text-surface-300 hover:!text-white w-full" 
                @click="$emit('exit')" 
            />
            
            <Button 
                v-if="total > 0"
                label="Pagamento" 
                icon="fa-solid fa-credit-card" 
                class="!bg-green-600 !border-none hover:!bg-green-500 text-white font-bold w-full shadow-lg shadow-green-900/20"
                @click="$emit('open-payment')"
            />

            <Button 
                v-else
                label="Liberar Mesa" 
                icon="fa-solid fa-lock-open" 
                class="!bg-red-500/10 !border !border-red-500/50 hover:!bg-red-500 hover:!text-white hover:!border-red-500 text-red-500 font-bold w-full transition-all"
                :loading="isVoiding"
                @click="handleVoidSession"
            />
        </div>
        
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { notify } from '@/services/notify';
import api from '@/services/api';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';

const emit = defineEmits(['exit', 'open-payment']);
const sessionStore = useSessionStore();
const router = useRouter();
const isVoiding = ref(false);

const items = computed(() => sessionStore.currentAccountItems || []);

// Filtra apenas itens não cancelados
const activeItems = computed(() => items.value.filter(i => i.status !== 'cancelled'));

const total = computed(() => {
    if (sessionStore.accountTotal) {
        return sessionStore.accountTotal(sessionStore.currentAccount);
    }
    return (items.value || []).reduce((acc, item) => {
        if (item.status === 'cancelled') return acc;
        return acc + (parseFloat(item.line_total) || 0);
    }, 0);
});

const subtotal = computed(() => total.value);
const formatMoney = (val) => 'R$ ' + (parseFloat(val) || 0).toFixed(2).replace('.', ',');

const handleCancelItem = async (item) => {
    if (!confirm(`Deseja cancelar ${item.name}?`)) return;
    if (item.id) {
        try {
            const res = await api.post('/cancel-item', { item_id: item.id });
            if (res.data.success) {
                notify('success', 'Item Cancelado', `${item.name} removido.`);
                await sessionStore.refreshSession();
            }
        } catch (e) {
            notify('error', 'Erro', 'Não foi possível cancelar o item.');
        }
    }
};

const handleVoidSession = async () => {
    if (!confirm('Deseja LIBERAR esta mesa e encerrar a sessão?')) return;
    
    isVoiding.value = true;
    try {
        const res = await api.post('/void-session', { session_id: sessionStore.sessionId });
        
        if (res.data.success) {
            notify('info', 'Mesa Liberada', 'A sessão foi encerrada sem pagamento.');
            sessionStore.leaveSession(); 
            router.push('/tables');      
        }
    } catch (error) {
        notify('error', 'Erro', 'Falha ao liberar mesa.');
    } finally {
        isVoiding.value = false;
    }
};
</script>