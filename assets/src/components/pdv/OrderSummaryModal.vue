<template>
  <div class="flex flex-col h-full bg-surface-900 text-white border-l border-surface-800">
    <div class="p-6 bg-surface-950 border-b border-surface-800 shrink-0 shadow-sm">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-[10px] font-black text-surface-500 uppercase tracking-[0.2em]">Total Selecionado</h3>
                <div class="text-4xl font-black text-primary-400 font-mono mt-1">
                    R$ {{ formatMoney(sessionStore.totalSelectedForPayment) }}
                </div>
            </div>
            <div class="text-right">
                <span class="text-[10px] text-surface-500 font-bold uppercase tracking-widest block mb-1">Itens Marcados</span>
                <span class="text-xl font-bold text-white">{{ sessionStore.selectedItemsForPayment.length }}</span>
            </div>
        </div>

        <div v-if="sessionStore.totals.total > 0" class="space-y-1.5">
            <div class="flex justify-between text-[10px] font-bold uppercase text-surface-500">
                <span>Saldo Devedor na Mesa</span>
                <span>R$ {{ formatMoney(sessionStore.totals.total - sessionStore.totalSelectedForPayment) }}</span>
            </div>
            <div class="w-full h-1.5 bg-surface-800 rounded-full overflow-hidden">
                <div 
                    class="h-full bg-primary-500 transition-all duration-500" 
                    :style="{ width: Math.min((sessionStore.totalSelectedForPayment / sessionStore.totals.total * 100), 100) + '%' }"
                ></div>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-6 scrollbar-thin">
        <div v-if="isLoadingMethods" class="flex justify-center p-10">
            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
        </div>

        <template v-else>
            <div v-for="cat in paymentCategories" :key="cat.category" class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-black text-surface-600 uppercase tracking-widest whitespace-nowrap">
                        {{ cat.category }}
                    </span>
                    <div class="h-px bg-surface-800 flex-1"></div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button 
                        v-for="method in cat.methods" 
                        :key="method.id"
                        class="flex flex-col items-center justify-center p-3 rounded-xl border transition-all gap-2 group"
                        :class="selectedMethod?.id === method.id 
                            ? 'bg-primary-500/10 border-primary-500 text-primary-400' 
                            : 'bg-surface-950 border-surface-800 text-surface-500 hover:border-surface-600'"
                        @click="selectedMethod = method"
                    >
                        <i :class="[method.icon, selectedMethod?.id === method.id ? 'text-primary-400' : 'group-hover:text-white']" class="text-xl"></i>
                        <span class="text-[10px] font-black uppercase text-center leading-tight">{{ method.label }}</span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <div class="p-6 bg-surface-950 border-t border-surface-800">
        <Button 
            :label="isProcessing ? 'PROCESSANDO...' : 'CONFIRMAR PAGAMENTO'" 
            :icon="isProcessing ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-check-double'" 
            class="w-full !h-16 !text-lg !font-black !border-none shadow-lg transition-all active:scale-[0.98]"
            :class="canPay ? '!bg-primary-600 hover:!bg-primary-500 !text-surface-950' : '!bg-surface-800 !text-surface-600'"
            :disabled="!canPay || isProcessing"
            @click="handlePayment"
        />
        <p class="text-[9px] text-center text-surface-600 uppercase font-bold tracking-[0.15em] mt-4">
            A mesa permanece aberta até a quitação total dos itens.
        </p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router'; // Importando Router
import { useSessionStore } from '@/stores/session-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';

const router = useRouter();
const route = useRoute();
const sessionStore = useSessionStore();
const isLoadingMethods = ref(false);
const isProcessing = ref(false);
const paymentCategories = ref([]);
const selectedMethod = ref(null);

const canPay = computed(() => {
    return sessionStore.totalSelectedForPayment > 0 && selectedMethod.value;
});

const fetchPaymentMethods = async () => {
    isLoadingMethods.value = true;
    try {
        const { data } = await api.get('/payment-methods?context=pos');
        if (data.success) paymentCategories.value = data.categories;
    } catch (e) {
        notify('error', 'Erro', 'Falha ao carregar pagamentos.');
    } finally {
        isLoadingMethods.value = false;
    }
};

const handlePayment = async () => {
    if (!canPay.value) return;
    
    isProcessing.value = true;
    try {
        const payload = {
            session_id: sessionStore.sessionId,
            method: selectedMethod.value.type,
            method_id: selectedMethod.value.id,
            amount: sessionStore.totalSelectedForPayment,
            items: sessionStore.selectedItemsForPayment,
            account: sessionStore.currentAccount
        };

        const { data } = await api.post('/pay-session', payload);
        if (data.success) {
            notify('success', 'Sucesso', 'Pagamento registrado.');
            sessionStore.selectedItemsForPayment = []; 
            selectedMethod.value = null;
            
            await sessionStore.refreshSession();
            
            if (sessionStore.totals.total <= 0) {
                // Se tudo foi pago, fecha sessão e vai pro grid
                sessionStore.leaveSession();
                router.push('/staff/tables');
            } else {
                // Se sobrou dívida (outras contas), mas estamos no modo "checkout rápido", voltamos pro grid
                // para o gerente poder selecionar outra conta ou mesa.
                if (route.query.mode === 'checkout') {
                    router.push('/staff/tables');
                }
            }
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha no processamento.');
    } finally {
        isProcessing.value = false;
    }
};

const formatMoney = (val) => (parseFloat(val) || 0).toFixed(2).replace('.', ',');

onMounted(fetchPaymentMethods);
</script>