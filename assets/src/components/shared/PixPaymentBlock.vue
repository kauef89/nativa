<template>
  <div class="bg-surface-900 border border-surface-800 rounded-2xl p-6 text-center animate-fade-in">
    
    <div v-if="loading" class="py-10">
        <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary-500 mb-4"></i>
        <p class="text-surface-400 font-bold">Gerando Pix Sicredi...</p>
    </div>

    <div v-else-if="expired" class="py-6 animate-fade-in">
        <div class="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center mx-auto mb-4 border border-red-500/30">
            <i class="fa-regular fa-clock text-red-500 text-3xl"></i>
        </div>
        <h3 class="text-xl font-black text-white mb-2">Tempo Esgotado</h3>
        <p class="text-surface-400 text-sm mb-6 max-w-xs mx-auto">
            O tempo para pagamento deste pedido expirou e ele foi cancelado automaticamente.
        </p>
        <Button label="Fazer Novo Pedido" class="!bg-surface-800 hover:!bg-surface-700 !border-none !font-black" @click="handleRestart" />
    </div>

    <div v-else-if="error" class="py-6">
        <i class="fa-solid fa-triangle-exclamation text-red-400 text-3xl mb-2"></i>
        <p class="text-white mb-4">{{ error }}</p>
        <Button label="Tentar Novamente" class="!bg-surface-800 font-black" @click="generatePix" />
    </div>

    <div v-else class="flex flex-col items-center">
        <div class="mb-6 flex flex-col items-center">
            <div class="text-[10px] uppercase font-black text-surface-500 tracking-widest mb-1">Expira em</div>
            <div class="text-4xl font-black tabular-nums" :class="timeLeft < 60 ? 'text-red-500 animate-pulse' : 'text-white'">
                {{ formattedTime }}
            </div>
        </div>

        <div class="bg-white p-3 rounded-xl shadow-lg mb-6 relative group transition-all duration-500" :class="{'opacity-50 blur-sm': expired}">
            <qrcode-vue :value="pixString" :size="200" level="H" />
            
            <div v-if="isPaid" class="absolute inset-0 bg-green-500/95 flex flex-col items-center justify-center rounded-lg animate-fade-in z-10">
                <i class="fa-solid fa-check-circle text-white text-5xl mb-2"></i>
                <span class="text-white font-black uppercase tracking-widest text-sm">Pago!</span>
            </div>
        </div>

        <div class="w-full max-w-sm">
            <p class="text-[10px] text-surface-500 uppercase font-black mb-2">Pix Copia e Cola</p>
            <div class="flex gap-2">
                <input 
                    type="text" 
                    readonly 
                    :value="pixString" 
                    class="flex-1 bg-surface-950 border border-surface-800 rounded-lg px-3 text-xs text-surface-300 font-bold tracking-wide truncate focus:outline-none"
                >
                <button 
                    @click="copyToClipboard" 
                    class="bg-primary-600 hover:bg-primary-500 text-white px-4 rounded-lg font-black text-xs transition-colors uppercase tracking-wider"
                >
                    Copiar
                </button>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-2 text-xs text-surface-500 font-medium" v-if="!isPaid">
            <i class="fa-solid fa-rotate fa-spin"></i> Aguardando confirmação do banco...
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QrcodeVue from 'qrcode.vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import { useSessionStore } from '@/stores/session-store';
import Button from 'primevue/button';

const props = defineProps({
    sessionId: { type: Number, required: true },
    autoStart: { type: Boolean, default: true }
});

const emit = defineEmits(['payment-confirmed']);
const sessionStore = useSessionStore();

const pixString = ref('');
const txid = ref('');
const loading = ref(false);
const error = ref(null);
const isPaid = ref(false);
const expired = ref(false);

const timeLeft = ref(300); 
let pollingInterval = null;
let timerInterval = null;

const formattedTime = computed(() => {
    const m = Math.floor(timeLeft.value / 60);
    const s = timeLeft.value % 60;
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
});

const generatePix = async () => {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await api.post('/generate-pix', { session_id: props.sessionId });
        if (data.success) {
            pixString.value = data.pix_copy_paste;
            txid.value = data.txid;
            
            if (data.created_at) {
                timeLeft.value = 300; 
            }
            
            startTimer();
            startPolling();
        } else {
            error.value = data.message;
        }
    } catch (e) {
        error.value = 'Falha ao conectar com Sicredi.';
    } finally {
        loading.value = false;
    }
};

const checkStatus = async () => {
    if (!txid.value || isPaid.value || expired.value) return;
    try {
        const { data } = await api.get(`/check-pix-status?txid=${txid.value}&session_id=${props.sessionId}`);
        
        if (data.paid) {
            handleSuccess();
        } else if (data.expired || data.status === 'cancelled') {
            handleExpiration();
        }
    } catch (e) { console.error('Erro polling pix', e); }
};

const startTimer = () => {
    stopTimer();
    timerInterval = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            handleExpiration();
        }
    }, 1000);
};

const handleExpiration = () => {
    expired.value = true;
    stopPolling();
    stopTimer();
    sessionStore.leaveSession(); 
};

const handleSuccess = () => {
    isPaid.value = true;
    stopPolling();
    stopTimer();
    notify('success', 'Sucesso!', 'Pagamento confirmado.');
    setTimeout(() => emit('payment-confirmed'), 2000);
};

const handleRestart = () => {
    sessionStore.leaveSession();
    window.location.reload();
};

const startPolling = () => {
    stopPolling();
    pollingInterval = setInterval(checkStatus, 5000);
};

const stopPolling = () => { if (pollingInterval) clearInterval(pollingInterval); };
const stopTimer = () => { if (timerInterval) clearInterval(timerInterval); };

const copyToClipboard = () => {
    navigator.clipboard.writeText(pixString.value);
    notify('info', 'Copiado', 'Código Pix copiado.');
};

onMounted(() => {
    if (props.autoStart) generatePix();
});

onUnmounted(() => {
    stopPolling();
    stopTimer();
});
</script>