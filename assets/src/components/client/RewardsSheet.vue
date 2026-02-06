<template>
  <Dialog 
    v-model:visible="isOpen" 
    modal 
    position="bottom" 
    :dismissableMask="true"
    class="!w-full !m-0 !max-w-md !mx-auto !bg-transparent !shadow-none"
    :contentStyle="{ padding: '0', background: 'transparent' }"
    :pt="{
        mask: { class: '!bg-surface-950/80 backdrop-blur-sm' },
        root: { class: '!border-0 !rounded-none' }
    }"
  >
    <div class="bg-surface-900 border-t border-surface-800 rounded-t-[2.5rem] shadow-[0_-10px_40px_rgba(0,0,0,0.6)] flex flex-col max-h-[90vh] relative overflow-hidden animate-slide-up">
        
        <div class="p-6 pb-4 shrink-0 relative z-20 bg-surface-950/50 backdrop-blur-sm border-b border-surface-800">
            <div class="w-12 h-1.5 bg-surface-700/50 rounded-full mx-auto mb-6"></div>
            
            <div class="flex justify-between items-end mb-4">
                <div>
                    <h2 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-star text-yellow-500"></i> Fidelidade
                    </h2>
                    <p class="text-surface-400 text-xs font-medium">Junte pontos e troque por delícias.</p>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-surface-500 font-bold uppercase tracking-widest block">Seu Saldo</span>
                    <span class="text-3xl font-black text-yellow-400">{{ formatThousand(userBalance) }}</span>
                    <span class="text-xs text-yellow-600 font-bold ml-1">pts</span>
                </div>
            </div>

            <div class="flex p-1 bg-surface-950 rounded-xl border border-surface-800">
                <button 
                    v-for="tab in ['catalogo', 'extrato']" 
                    :key="tab"
                    class="flex-1 py-2 text-xs font-black uppercase tracking-wider rounded-lg transition-all"
                    :class="activeTab === tab ? 'bg-surface-800 text-white shadow' : 'text-surface-500 hover:text-surface-300'"
                    @click="activeTab = tab"
                >
                    {{ tab === 'catalogo' ? 'Prêmios' : 'Extrato' }}
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto relative z-10 bg-surface-900">
            
            <div v-if="loading" class="flex flex-col items-center justify-center py-20 space-y-4">
                <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
            </div>

            <div v-else-if="activeTab === 'catalogo'" class="p-4 space-y-4">
                
                <div v-if="rewards.length === 0" class="text-center py-10 opacity-50">
                    <i class="fa-solid fa-gift text-4xl mb-3"></i>
                    <p class="text-sm font-bold">Nenhum prêmio disponível no momento.</p>
                </div>

                <div v-else v-for="reward in rewards" :key="reward.id" 
                     class="group relative bg-surface-800 border border-surface-700 rounded-2xl p-3 flex gap-4 transition-all"
                     :class="{'opacity-50 grayscale': userBalance < reward.points_cost}"
                >
                    <div class="w-20 h-20 rounded-xl bg-surface-900 shrink-0 overflow-hidden border border-surface-700">
                        <img v-if="reward.image" :src="reward.image" class="w-full h-full object-cover">
                        <div v-else class="flex items-center justify-center h-full text-surface-700"><i class="fa-solid fa-gift text-xl"></i></div>
                    </div>

                    <div class="flex-1 min-w-0 flex flex-col justify-between py-1">
                        <div>
                            <h3 class="font-bold text-white text-sm leading-tight mb-1">{{ reward.name }}</h3>
                            <span class="text-yellow-500 font-black text-sm flex items-center gap-1">
                                {{ formatThousand(reward.points_cost) }} <span class="text-[10px] opacity-80 font-bold text-surface-400 uppercase">pontos</span>
                            </span>
                        </div>

                        <div class="mt-2">
                            <button 
                                v-if="userBalance >= reward.points_cost"
                                class="bg-primary-600 hover:bg-primary-500 text-white text-xs font-black px-4 py-2 rounded-lg w-full transition-colors flex items-center justify-center gap-2 shadow-lg shadow-primary-900/20 active:scale-95 uppercase tracking-wide"
                                @click="redeemReward(reward)"
                            >
                                <i class="fa-solid fa-cart-plus"></i> Resgatar
                            </button>
                            
                            <div v-else class="w-full bg-surface-950 border border-surface-800 text-surface-500 text-[10px] font-bold px-3 py-1.5 rounded-lg text-center flex items-center justify-center gap-2">
                                <i class="fa-solid fa-lock"></i> Faltam {{ formatThousand(reward.points_cost - userBalance) }} pts
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-4 space-y-3">
                <div v-if="history.length === 0" class="text-center py-10 opacity-50">
                    <p class="text-sm font-bold">Nenhuma movimentação recente.</p>
                </div>

                <div v-for="entry in history" :key="entry.id" class="flex items-center justify-between p-3 bg-surface-950/30 border border-surface-800 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg border shrink-0"
                             :class="entry.points > 0 ? 'bg-green-500/10 text-green-500 border-green-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20'">
                            <i class="fa-solid" :class="entry.points > 0 ? 'fa-arrow-trend-up' : 'fa-gift'"></i>
                        </div>
                        <div>
                            <p class="text-xs text-surface-300 font-bold leading-tight">{{ entry.description }}</p>
                            <span class="text-[10px] text-surface-500 font-medium">{{ entry.date }}</span>
                        </div>
                    </div>
                    <div class="font-black" :class="entry.points > 0 ? 'text-green-400' : 'text-red-400'">
                        {{ entry.points > 0 ? '+' : '' }}{{ formatThousand(entry.points) }}
                    </div>
                </div>
            </div>

        </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref } from 'vue';
import api from '@/services/api';
import { useCartStore } from '@/stores/cart-store';
import { useFormat } from '@/composables/useFormat'; 
import { notify } from '@/services/notify';
import Dialog from 'primevue/dialog';

const cartStore = useCartStore();
const { formatThousand } = useFormat(); 

const isOpen = ref(false);
const activeTab = ref('catalogo');
const loading = ref(false);

const userBalance = ref(0);
const rewards = ref([]);
const history = ref([]);

const open = () => {
    isOpen.value = true;
    fetchData();
};

const fetchData = async () => {
    loading.value = true;
    try {
        const resRewards = await api.get('/loyalty/rewards');
        if (resRewards.data.success) {
            rewards.value = resRewards.data.rewards;
            userBalance.value = resRewards.data.user_balance;
        }

        const resHistory = await api.get('/loyalty/history');
        if (resHistory.data.success) {
            history.value = resHistory.data.history;
        }
    } catch (e) {
        notify('error', 'Erro', 'Falha ao carregar fidelidade.');
    } finally {
        loading.value = false;
    }
};

const redeemReward = (reward) => {
    if (confirm(`Resgatar "${reward.name}" por ${formatThousand(reward.points_cost)} pontos?`)) {
        cartStore.addItem({
            id: reward.id,
            name: reward.name,
            image: reward.image,
            price: 0, 
            qty: 1,
            modifiers: [],
            is_reward: true,
            points_cost: reward.points_cost,
            uniqueId: Date.now()
        });
        
        notify('success', 'Prêmio Adicionado', 'Finalize o pedido para resgatar.');
        isOpen.value = false;
        cartStore.isOpen = true; 
    }
};

defineExpose({ open });
</script>

<style scoped>
.animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>