<template>
  <div class="flex flex-col h-full bg-transparent text-surface-0 relative">
    
    <div class="p-6 border-b border-surface-3/10 shrink-0 z-10">
        <h3 class="text-[10px] font-black text-surface-500 uppercase tracking-widest mb-1">
            Total Selecionado
        </h3>
        <div class="text-4xl font-black text-primary-400 leading-none">
            {{ formatCurrency(sessionStore.totalSelectedForPayment) }}
        </div>
        <div v-if="sessionStore.clientName && sessionStore.clientName !== 'Cliente Balcão'" class="mt-3 flex items-center gap-2">
            <span class="text-[10px] bg-surface-3 text-surface-300 px-2 py-1 rounded-lg uppercase font-bold tracking-wider">
                <i class="fa-solid fa-user-tag mr-1"></i> {{ sessionStore.clientName }}
            </span>
        </div>
    </div>

    <div class="flex-1 overflow-hidden relative flex flex-col">
        
        <div v-if="!isOrderPaid" class="h-full flex flex-col">
            <div v-if="sessionStore.totalSelectedForPayment <= 0" class="flex-1 flex flex-col items-center justify-center text-surface-600 opacity-50 p-6 text-center select-none">
                <i class="fa-solid fa-arrow-left text-3xl mb-4 animate-pulse"></i>
                <p class="text-xs font-black uppercase tracking-wide">Selecione itens à esquerda<br>para pagar.</p>
            </div>

            <div v-else class="flex-1 overflow-y-auto scrollbar-thin px-4">
                <PaymentWidget 
                    :totalDue="sessionStore.totalSelectedForPayment"
                    mode="settlement"
                    :showFooter="false" 
                    :initialPayments="promisedPayments" 
                    @update:payments="handlePaymentsUpdate"
                    @validity-change="isPaymentValid = $event"
                    class="!bg-transparent !border-none"
                />
            </div>
        </div>

        <div v-else class="flex-1 flex flex-col items-center justify-center p-6 space-y-6 animate-fade-in">
            <div class="w-24 h-24 bg-green-500/10 rounded-full flex items-center justify-center border-2 border-green-500/20 shadow-[0_0_40px_rgba(34,197,94,0.3)]">
                <i class="fa-solid fa-check text-5xl text-green-500"></i>
            </div>
            <div class="text-center">
                <h3 class="text-2xl font-black text-surface-0">Conta Quitada</h3>
                <p class="text-surface-400 text-sm font-bold mt-1">Pagamento registrado.</p>
            </div>
        </div>

    </div>

    <div v-if="!isOrderPaid" class="p-6 border-t border-surface-3/10 shrink-0 z-20">
        <Button 
            :label="isProcessing ? 'PROCESSANDO...' : 'CONFIRMAR PAGAMENTO'" 
            :icon="isProcessing ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-check-double'" 
            class="w-full !h-14 !text-lg !font-black !border-none shadow-xl transition-all !rounded-full"
            :class="canPay ? '!bg-primary-600 hover:!bg-primary-500 !text-surface-950' : '!bg-surface-3 !text-surface-500 cursor-not-allowed'"
            :disabled="!canPay || isProcessing"
            @click="handlePayment"
        />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useFormat } from '@/composables/useFormat'; 
import api from '@/services/api'; 
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import InputMask from 'primevue/inputmask';
import PaymentWidget from '@/components/shared/PaymentWidget.vue';

// Definição dos Eventos
const emit = defineEmits(['open-payment', 'toggle-history', 'payment-success', 'require-identification']);

const sessionStore = useSessionStore();
const { formatCurrency, cleanDigits } = useFormat(); 

const isProcessing = ref(false);
const isPaymentValid = ref(false);
const currentPayments = ref([]); 

// Estados Fiscais (Mantidos)
const isEmitting = ref(false);
const fiscalUrl = ref(null);

const isOrderPaid = computed(() => {
    if (sessionStore.sessionStatus === 'paid' || sessionStore.sessionStatus === 'closed') return true;
    if (sessionStore.totals.total <= 0.01 && sessionStore.items.length > 0) return true;
    return false;
});

const promisedPayments = computed(() => {
    if (!sessionStore.paymentsPromised || sessionStore.paymentsPromised.length === 0) return [];
    
    const totalSelected = sessionStore.totalSelectedForPayment;
    const sessionTotal = sessionStore.totals.total;
    
    if (Math.abs(totalSelected - sessionTotal) < 1.0) {
        return sessionStore.paymentsPromised;
    }
    return [];
});

const handlePaymentsUpdate = (payments) => {
    currentPayments.value = payments;
};

const canPay = computed(() => {
    return sessionStore.totalSelectedForPayment > 0 && isPaymentValid.value;
});

const handlePayment = async () => {
    // --- REGRA DE IDENTIFICAÇÃO (CORREÇÃO) ---
    if (sessionStore.sessionType === 'counter' && sessionStore.currentAccount === 'Principal') {
        notify('warn', 'Identificação Necessária', 'Identifique a conta antes de receber o pagamento.');
        emit('require-identification');
        return;
    }
    // ----------------------------

    if (!canPay.value) return;
    isProcessing.value = true;

    try {
        const payload = {
            session_id: sessionStore.sessionId,
            payments: currentPayments.value, 
            items: sessionStore.selectedItemsForPayment,
            account: sessionStore.currentAccount
        };

        const { data } = await api.post('/pay-session', payload);
        
        if (data.success) {
            emit('payment-success', { 
                amount: sessionStore.totalSelectedForPayment, 
                payments: currentPayments.value 
            });

            sessionStore.selectedItemsForPayment = []; 
            currentPayments.value = [];
            await sessionStore.refreshSession();
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha no pagamento.');
    } finally {
        isProcessing.value = false;
    }
};

const handleEmitFiscal = async () => {
    if (!confirm('Deseja emitir a NFC-e para esta venda?')) return;

    isEmitting.value = true;
    try {
        const cleanCpf = cleanDigits(''); // Pode implementar input de CPF depois se necessário
        const { data } = await api.post('/fiscal/emit-local', {
            session_id: sessionStore.sessionId,
            cpf: cleanCpf
        });

        if (data.success) {
            fiscalUrl.value = data.danfe_url; 
            notify('success', 'Nota Emitida', data.message || 'NFC-e autorizada!');
            openDanfe();
        } 
    } catch (e) {
        const msg = e.response?.data?.message || 'Erro ao comunicar com o emissor.';
        notify('error', 'Erro Fiscal', msg);
    } finally {
        isEmitting.value = false;
    }
};

const openDanfe = () => {
    if (fiscalUrl.value) {
        window.open(fiscalUrl.value, '_blank');
    }
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>