<template>
  <div class="flex flex-col h-full animate-fade-in">
    
    <div class="p-4 border-b border-surface-3/10 flex justify-between items-center bg-surface-2 rounded-t-[20px] mb-2 shadow-sm">
        
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-primary-500 text-xs"></i>
            <span class="font-black text-surface-0 text-base uppercase tracking-tight">
                Mesa {{ table?.number }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <span v-if="table?.status === 'occupied'" class="font-black text-surface-0 text-sm tracking-tighter">
                {{ formatCurrency(totalTableValue) }}
            </span>

            <span class="text-[9px] uppercase font-black px-2 py-1 rounded-md tracking-wider"
                  :class="table?.status === 'occupied' ? 'bg-primary-500 text-surface-950' : 'bg-surface-3 text-surface-400'">
                {{ table?.status === 'occupied' ? 'OCUPADA' : 'LIVRE' }}
            </span>
        </div>
    </div>

    <div class="flex flex-col gap-2 p-1" :class="{'pb-6': isMobile}">

        <template v-if="table?.status !== 'occupied'">
            <button
                class="premium-action-btn group"
                @click="$emit('action', { type: 'open' })"
            >
                <div class="premium-icon-box bg-surface-2 text-primary-500 group-hover:bg-primary-500 group-hover:text-surface-950">
                    <i class="fa-solid fa-power-off text-xl"></i>
                </div>
                <div class="flex flex-col text-left">
                    <span class="text-base font-black text-surface-0 leading-tight">ABRIR MESA</span>
                    <span class="text-xs text-surface-400 font-medium">Iniciar nova sessão</span>
                </div>
            </button>

            <button 
                class="secondary-btn group mt-2"
                :class="table?.is_active ? 'hover:!bg-red-500/10' : 'hover:!bg-green-500/10'"
                @click="$emit('action', { type: 'toggle_active', payload: table })"
            >
                <i class="fa-solid text-sm" :class="table?.is_active ? 'fa-ban text-red-400' : 'fa-check-circle text-green-400'"></i>
                <span class="text-xs font-bold uppercase tracking-wider" :class="table?.is_active ? 'text-red-400' : 'text-green-400'">
                    {{ table?.is_active ? 'Inativar Mesa' : 'Ativar Mesa' }}
                </span>
            </button>
        </template>

        <template v-else>
            
            <div class="grid grid-cols-2 gap-3 mb-2">
                <button class="premium-small-btn group" @click="$emit('action', { type: 'pos_default' })">
                    <i class="fa-solid fa-utensils text-primary-500 mb-1"></i>
                    <span class="text-[10px] font-black uppercase text-surface-200 group-hover:text-surface-0">Novo Pedido</span>
                </button>

                <button class="premium-small-btn group" @click="$emit('action', { type: 'new_account' })">
                    <i class="fa-solid fa-user-plus text-green-400 mb-1"></i>
                    <span class="text-[10px] font-black uppercase text-surface-200 group-hover:text-surface-0">Nova Conta</span>
                </button>
            </div>

            <div class="bg-surface-2/50 rounded-[20px] p-2 border border-surface-3/10 overflow-hidden">
                <div class="px-2 pb-2 flex justify-between items-center">
                    <span class="text-[10px] uppercase font-black text-surface-500 tracking-widest">Contas Abertas</span>
                </div>

                <div class="flex flex-col gap-2 max-h-60 overflow-y-auto scrollbar-thin">
                    <div v-for="acc in accounts" :key="acc.name"
                        class="flex items-center justify-between p-3 rounded-[16px] bg-surface-1 border border-surface-3/20 transition-all hover:border-surface-3/50"
                    >
                        <div class="flex flex-col min-w-0 pr-2">
                            <span class="text-sm font-black text-surface-0 truncate leading-tight">{{ acc.name }}</span>
                            <span class="text-xs font-bold text-surface-400 mt-0.5">{{ formatCurrency(acc.total) }}</span>
                        </div>

                        <div class="flex gap-2 shrink-0">
                            <button 
                                class="w-9 h-9 rounded-full bg-primary-500/10 text-primary-400 flex items-center justify-center hover:bg-primary-500 hover:text-surface-950 transition-colors shadow-sm"
                                v-tooltip.top="'Lançar Pedido'"
                                @click="$emit('action', { type: 'pos_account', payload: acc.name, mode: 'order' })"
                            >
                                <i class="fa-solid fa-plus text-xs font-black"></i>
                            </button>

                            <button 
                                class="w-9 h-9 rounded-full bg-surface-3 text-surface-300 flex items-center justify-center hover:bg-surface-0 hover:text-surface-950 transition-colors shadow-sm"
                                v-tooltip.top="'Ver Conta / Pagar'"
                                @click="$emit('action', { type: 'pos_account', payload: acc.name, mode: 'checkout' })"
                            >
                                <i class="fa-solid fa-ticket text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mt-2">
                <button class="secondary-btn group" @click="$emit('action', { type: 'swap' })">
                    <i class="fa-solid fa-arrow-right-arrow-left text-surface-400 group-hover:text-surface-0"></i>
                    <span>Trocar</span>
                </button>
                
                <button v-if="canVoid" 
                    class="secondary-btn group !bg-red-500/5 hover:!bg-red-500/20"
                    @click="$emit('action', { type: 'void' })"
                >
                    <i class="fa-solid fa-lock-open text-red-400"></i>
                    <span class="text-red-400 group-hover:text-red-300">Liberar</span>
                </button>
            </div>

        </template>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
const { formatCurrency } = useFormat();

const props = defineProps({
    table: Object,
    accounts: { type: Array, default: () => [] },
    loading: Boolean,
    canVoid: Boolean,
    isMobile: Boolean
});

defineEmits(['action']);

const totalTableValue = computed(() => {
    return props.accounts.reduce((sum, acc) => sum + (parseFloat(acc.total) || 0), 0);
});
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.premium-action-btn {
    @apply w-full p-4 rounded-[24px] bg-surface-2 hover:bg-surface-3 transition-all duration-300 flex items-center gap-4 relative overflow-hidden active:scale-[0.98] border border-surface-3/10 shadow-sm;
}
.premium-icon-box {
    @apply w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 shadow-inner border border-surface-3/20;
}
.premium-small-btn {
    @apply flex flex-col items-center justify-center p-3 rounded-[18px] bg-surface-2 hover:bg-surface-3 border border-surface-3/10 transition-all active:scale-95 shadow-sm;
}
.secondary-btn {
    @apply flex items-center justify-center gap-2 p-3 rounded-[16px] bg-surface-2 hover:bg-surface-3 text-xs font-bold text-surface-400 uppercase tracking-wider transition-all active:scale-95 border border-surface-3/10;
}
</style>