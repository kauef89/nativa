<template>
  <Dialog 
    :visible="visible" 
    modal 
    :closable="false"
    :dismissableMask="false"
    :style="{ width: '500px' }"
    :pt="{
        root: { class: '!bg-surface-1 !border-none !rounded-[24px] !shadow-2xl' },
        header: { class: '!bg-transparent !p-6 !pb-2' },
        content: { class: '!bg-transparent !p-6' }
    }"
  >
    <template #header>
        <div class="w-full text-center">
            <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-green-500/20 animate-bounce">
                <i class="fa-solid fa-check text-surface-950 text-3xl"></i>
            </div>
            <h2 class="text-2xl font-black text-surface-0">Venda Finalizada!</h2>
            <p class="text-surface-400 text-sm font-bold">O que deseja fazer agora?</p>
        </div>
    </template>

    <div class="flex flex-col gap-6">
        
        <div class="bg-surface-2 p-4 rounded-2xl border border-surface-3/10">
            <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest block mb-2">CPF na Nota (Opcional)</label>
            <div class="flex gap-2">
                <InputMask 
                    v-model="cpf" 
                    mask="999.999.999-99" 
                    placeholder="000.000.000-00" 
                    class="!bg-surface-1 !border-none !text-surface-0 !flex-1 !rounded-xl !font-bold text-center !h-12 focus:!ring-2 focus:!ring-primary-500"
                />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            
            <button class="action-btn bg-surface-2 hover:bg-surface-3 text-surface-200" @click="handlePrint('non_fiscal')">
                <i class="fa-solid fa-receipt text-xl mb-1 text-primary-400"></i>
                <span>Recibo Simples</span>
            </button>

            <button class="action-btn bg-surface-2 hover:bg-surface-3 text-surface-200" @click="handlePrint('fiscal')">
                <i class="fa-solid fa-qrcode text-xl mb-1 text-orange-400"></i>
                <span>Nota Fiscal (NFC-e)</span>
            </button>

            <button class="action-btn bg-surface-2 hover:bg-surface-3 text-surface-200 col-span-2" @click="handlePrint('both')">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-print"></i>
                    <span>Imprimir Ambos</span>
                </div>
            </button>
        </div>

        <Button 
            label="Não Imprimir (Finalizar)" 
            class="!w-full !h-14 !bg-primary-600 hover:!bg-primary-500 !text-surface-950 !font-black !rounded-full !text-lg shadow-xl"
            @click="finish"
        />

    </div>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputMask from 'primevue/inputmask';
import { PrinterService } from '@/services/printer-service';
import api from '@/services/api';
import { notify } from '@/services/notify';
import { useFormat } from '@/composables/useFormat';

const props = defineProps(['visible', 'orderData']);
const emit = defineEmits(['close']);
const { cleanDigits } = useFormat();

const cpf = ref('');

// Preenche CPF se o cliente já tiver cadastro
watch(() => props.visible, (val) => {
    if (val && props.orderData?.client_cpf) {
        cpf.value = props.orderData.client_cpf;
    } else {
        cpf.value = '';
    }
});

const handlePrint = async (mode) => {
    const order = props.orderData;
    const cleanCpf = cleanDigits(cpf.value);

    // 1. Recibo Não Fiscal
    if (mode === 'non_fiscal' || mode === 'both') {
        PrinterService.printPaymentReceipt(order);
    }

    // 2. Nota Fiscal
    // 2. Nota Fiscal
    if (mode === 'fiscal' || mode === 'both') {
        try {
            notify('info', 'Sefaz', 'Emitindo NFC-e... Aguarde.');
            const { data } = await api.post('/fiscal/emit-local', {
                session_id: order.id,
                cpf: cleanCpf
            });
            
            if (data.success) {
                notify('success', 'Sucesso', 'NFC-e Autorizada!');
                // Abre o PDF em nova aba
                if (data.danfe_url) {
                    window.open(data.danfe_url, '_blank');
                }
            }
        } catch (e) {
            console.error(e);
            notify('error', 'Erro Fiscal', e.response?.data?.message || 'Falha na emissão. Verifique o cadastro do produto.');
        }
    }

    // Fecha após comando (opcional: esperar resposta)
    finish();
};

const finish = () => {
    emit('close');
};
</script>

<style scoped>
.action-btn {
    @apply flex flex-col items-center justify-center p-4 rounded-xl border border-transparent transition-all active:scale-95 font-bold text-xs uppercase tracking-wide;
}
</style>