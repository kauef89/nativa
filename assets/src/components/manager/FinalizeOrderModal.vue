<template>
  <Dialog 
    :visible="visible" 
    @update:visible="emit('update:visible', $event)"
    modal 
    header="Baixa de Entrega" 
    :style="{ width: '500px' }"
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' },
        header: { class: '!bg-surface-950 !text-white !border-b !border-surface-800' },
        content: { class: '!bg-surface-900 !p-6' }
    }"
  >
    <div v-if="order" class="flex flex-col gap-4">
        
        <div class="bg-primary-500/10 border border-primary-500/20 p-3 rounded-xl flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-primary-500/20 flex items-center justify-center text-primary-500">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <div>
                <p class="text-xs text-primary-200 font-bold uppercase">Pedido #{{ order.id }} - {{ order.client }}</p>
                <p class="text-[10px] text-primary-300">Confirme o recebimento com o entregador.</p>
            </div>
        </div>

        <PaymentWidget 
            :totalDue="parseFloat(order.total)" 
            :initialPayments="parsedPromises"
            mode="settlement"
            @confirm="confirmPayment"
        />
        
        <div class="flex items-center justify-between p-3 bg-surface-950 rounded-xl border border-surface-800">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-star text-yellow-500"></i>
                <span class="text-sm font-bold text-surface-200">Pedir Avaliação 5 Estrelas?</span>
            </div>
            <ToggleSwitch v-model="requestReview" />
        </div>

        <div class="text-center">
            <Button label="Cancelar / Fechar" text class="!text-surface-500 hover:!text-white !text-xs font-bold" @click="emit('update:visible', false)" />
        </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import ToggleSwitch from 'primevue/toggleswitch'; // Importar Toggle
import PaymentWidget from '@/components/shared/PaymentWidget.vue';

const props = defineProps(['visible', 'order']);
const emit = defineEmits(['update:visible', 'success']);

const loading = ref(false);
const requestReview = ref(true); // Padrão: Pedir avaliação

const parsedPromises = computed(() => {
    if (!props.order) return [];
    if (Array.isArray(props.order.payments_promised)) {
        return props.order.payments_promised;
    }
    return [];
});

const confirmPayment = async (finalPayments) => {
    loading.value = true;
    try {
        const payload = {
            session_id: props.order.id,
            account: props.order.client,
            payments: finalPayments,
            request_review: requestReview.value // Envia a decisão
        };

        const { data } = await api.post('/pay-session', payload);
        
        if (data.success) {
            notify('success', 'Pedido Baixado', data.message || 'Valores lançados no caixa.');
            emit('success');
            emit('update:visible', false);
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha ao baixar pedido.');
    } finally {
        loading.value = false;
    }
};
</script>