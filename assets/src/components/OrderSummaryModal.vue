<template>
  <Dialog 
    v-model:visible="isOpen" 
    modal 
    header="Pagamento" 
    :style="{ width: '500px' }"
    @show="refreshData"
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' },
        header: { class: '!bg-surface-900 !border-b !border-surface-800 !p-5' },
        content: { class: '!bg-surface-900 !p-5' },
        closeButton: { class: '!text-surface-400 hover:!text-white hover:!bg-surface-800' }
    }"
  >
    <div class="flex flex-col h-full">
      
      <div class="bg-surface-950 p-4 rounded-xl border border-surface-800 mb-6 overflow-y-auto max-h-64">
        <div v-if="sessionStore.isLoading" class="flex justify-center p-3">
            <ProgressSpinner style="width: 30px; height: 30px" />
        </div>
        
        <ul v-else class="space-y-2">
            <li v-for="(item, index) in sessionStore.orderItems" :key="index" class="flex justify-between text-surface-300 text-sm">
                <span :class="{'line-through text-surface-600': item.status === 'cancelled'}">
                    <span class="font-bold text-surface-100">{{ item.qty }}x</span> {{ item.name }}
                    <span v-if="item.status === 'cancelled'" class="text-red-500 text-xs ml-1">(CANCELADO)</span>
                </span>
                <span class="font-bold">{{ formatMoney(item.line_total) }}</span>
            </li>
        </ul>
      </div>

      <div class="flex justify-between items-center mb-6 px-2">
        <span class="text-lg font-bold text-surface-400">Total a Pagar</span>
        <span class="text-3xl font-bold text-primary-400">R$ {{ formatMoney(sessionStore.orderTotal) }}</span>
      </div>

      <div class="w-full h-px bg-surface-800 mb-6"></div>

      <div v-if="isLoadingMethods" class="flex justify-center">
        <ProgressSpinner />
      </div>

      <div v-else class="grid grid-cols-2 gap-4 mb-6">
        <div v-for="method in paymentMethods" :key="method.id">
            <button 
                class="w-full p-4 rounded-xl border flex flex-col items-center justify-center transition-all duration-200 hover:-translate-y-1"
                :class="selectedMethodId === method.id ? 'bg-primary-600 border-primary-500 text-white shadow-lg shadow-primary-900/50' : 'bg-surface-800 border-surface-700 text-surface-300 hover:bg-surface-700 hover:text-white'"
                @click="selectedMethodId = method.id"
            >
                <i :class="method.icon" class="text-2xl mb-2"></i>
                <span class="font-bold text-sm">{{ method.label }}</span>
            </button>
        </div>
      </div>

      <div class="mt-auto">
        <Button 
            label="CONFIRMAR PAGAMENTO" 
            icon="pi pi-check-circle" 
            class="w-full h-14 font-bold text-lg !bg-green-600 hover:!bg-green-500 !border-none !text-white"
            :loading="isProcessing"
            :disabled="!selectedMethodId || sessionStore.orderTotal <= 0"
            @click="processPayment"
        />
      </div>

    </div>
  </Dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import api from '@/services/api';

const sessionStore = useSessionStore();
const isOpen = ref(false);
const isLoadingMethods = ref(false);
const isProcessing = ref(false);
const paymentMethods = ref([]);
const selectedMethodId = ref(null);

const formatMoney = (val) => {
  const num = parseFloat(val);
  if (isNaN(num)) return '0,00';
  return num.toFixed(2).replace('.', ',');
};

const refreshData = () => {
  sessionStore.fetchOrderSummary();
};

const fetchPaymentMethods = async () => {
  if (paymentMethods.value.length > 0) return;
  isLoadingMethods.value = true;
  try {
    const response = await api.get('/payment-methods');
    if (response.data.success) {
        paymentMethods.value = response.data.methods.map(m => ({
            ...m,
            icon: m.icon.includes('money') ? 'pi pi-money-bill' : 'pi pi-credit-card'
        }));
    }
  } catch (error) {
    console.error(error);
  } finally {
    isLoadingMethods.value = false;
  }
};

const processPayment = async () => {
  if (!selectedMethodId.value) return;
  isProcessing.value = true;
  const methodObj = paymentMethods.value.find(m => m.id === selectedMethodId.value);
  try {
    const response = await api.post('/pay-session', {
      session_id: sessionStore.sessionId,
      method: methodObj.type, method_id: methodObj.id, amount: sessionStore.orderTotal
    });
    if (response.data.success) {
      isOpen.value = false;
      sessionStore.$reset(); 
    }
  } catch (error) {
    alert('Erro no pagamento.');
  } finally {
    isProcessing.value = false;
  }
};

onMounted(() => {
  fetchPaymentMethods();
});

defineExpose({ isOpen });
</script>