<template>
  <BottomSheet 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    :title="`Mesa ${sessionStore.identifier}`"
    :subtitle="sessionStore.currentAccount"
  >
    <template #actions>
        <Button icon="fa-solid fa-print" text rounded class="!text-surface-400 hover:!text-surface-0 !w-8 !h-8" @click="printAll" v-tooltip="'Imprimir Conferência'" />
    </template>

    <div class="flex flex-col gap-6 pb-20">
        
        <div v-if="pendingItems.length > 0" class="space-y-3">
            <div class="text-[10px] font-black text-surface-500 uppercase tracking-widest px-1 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                Ativos
            </div>

            <div v-for="(item, idx) in pendingItems" :key="item.uniqueId || idx" 
                 class="relative overflow-hidden rounded-xl bg-surface-2 border border-surface-3/10 transition-all select-none"
            >
                <div class="flex justify-between items-start p-3 cursor-pointer active:bg-surface-3 transition-colors"
                     :class="{'opacity-20 blur-[1px]': activeItemId === (item.uniqueId || item.id)}"
                     @click="toggleOverlay(item)"
                >
                    <div class="flex gap-3 min-w-0">
                        <div class="bg-surface-3 text-surface-200 w-8 h-8 rounded-lg flex items-center justify-center text-xs font-black shrink-0 border border-surface-4/10">
                            {{ item.qty }}x
                        </div>
                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-bold text-surface-100 leading-tight truncate pr-2">{{ item.name }}</span>
                            <div v-if="item.modifiers?.length" class="text-[10px] text-surface-400 mt-1 leading-snug">
                                {{ item.modifiers.map(m => m.name).join(', ') }}
                            </div>
                            
                            <div class="flex gap-2 mt-1.5">
                                <span v-if="item.status === 'printed'" class="text-[9px] text-orange-400 font-bold uppercase flex items-center gap-1 bg-orange-500/10 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-fire-burner"></i> Cozinha
                                </span>
                                <span v-if="['swap_pending', 'cancellation_requested'].includes(item.status)" class="text-[9px] text-purple-400 font-bold uppercase flex items-center gap-1 bg-purple-500/10 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-hourglass"></i> Em Análise
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="text-sm font-black text-surface-0 shrink-0">
                        {{ formatCurrency(item.line_total) }}
                    </div>
                </div>

                <transition name="fade">
                    <div v-if="activeItemId === (item.uniqueId || item.id)"
                         class="absolute inset-0 z-20 flex items-center justify-evenly bg-surface-2/95 backdrop-blur-sm animate-fade-in px-2"
                         @click.stop
                    >
                        <button class="overlay-btn text-blue-400 bg-blue-500/10 hover:bg-blue-500 hover:text-white" 
                                @click="triggerAction('print', item)">
                            <i class="fa-solid fa-print"></i>
                        </button>

                        <button class="overlay-btn text-orange-400 bg-orange-500/10 hover:bg-orange-500 hover:text-white" 
                                @click="triggerAction('edit', item)">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>

                        <button class="overlay-btn text-purple-400 bg-purple-500/10 hover:bg-purple-500 hover:text-white" 
                                @click="triggerAction('swap', item)">
                            <i class="fa-solid fa-rotate"></i>
                        </button>

                        <button class="overlay-btn text-red-400 bg-red-500/10 hover:bg-red-500 hover:text-white" 
                                @click="triggerAction('delete', item)">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>

                        <div class="w-px h-6 bg-surface-0/10 mx-1"></div>

                        <button class="overlay-btn text-surface-400 hover:text-surface-0 bg-surface-3 hover:bg-surface-4" 
                                @click="activeItemId = null">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </transition>
            </div>
        </div>

        <div v-if="paidItems.length > 0" class="space-y-3 opacity-60 grayscale">
            <div class="text-[10px] font-black text-surface-500 uppercase tracking-widest px-1 flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-surface-500"></div>
                Pagos / Finalizados
            </div>

            <div v-for="(item, idx) in paidItems" :key="item.uniqueId || idx" 
                 class="rounded-xl bg-surface-1 border border-surface-3/10 p-3 flex justify-between items-center"
            >
                <div class="flex gap-3 items-center">
                    <span class="text-xs font-bold text-surface-400">{{ item.qty }}x</span>
                    <span class="text-sm font-bold text-surface-400 line-through">{{ item.name }}</span>
                </div>
                <span class="text-xs font-bold text-surface-500">{{ formatCurrency(item.line_total) }}</span>
            </div>
        </div>

        <div v-if="pendingItems.length === 0 && paidItems.length === 0" class="text-center py-10 text-surface-500 opacity-60">
            <i class="fa-solid fa-receipt text-4xl mb-3"></i>
            <p class="text-sm font-bold">Nenhum item lançado.</p>
        </div>

        <div class="bg-surface-2 p-4 rounded-[20px] border border-surface-3/10 space-y-2 mt-auto">
            <div class="flex justify-between text-xs text-surface-400 font-bold uppercase">
                <span>Subtotal</span>
                <span>{{ formatCurrency(totals.subtotal) }}</span>
            </div>
            <div class="flex justify-between text-xs text-surface-400 font-bold uppercase" v-if="sessionStore.deliveryFee > 0">
                <span>Taxa/Serviço</span>
                <span>{{ formatCurrency(sessionStore.deliveryFee) }}</span>
            </div>
            <div class="border-t border-surface-3/10 my-1"></div>
            <div class="flex justify-between items-center">
                <span class="text-sm font-black text-surface-0 uppercase">Total Aberto</span>
                <span class="text-2xl font-black text-primary-400">{{ formatCurrency(totals.total) }}</span>
            </div>
        </div>

        <div class="bg-surface-2 rounded-[20px] border border-surface-3/10 overflow-hidden" v-if="pendingItems.length > 0">
            <div class="bg-surface-3/30 px-4 py-2 border-b border-surface-3/10">
                <span class="text-[10px] font-black text-surface-400 uppercase tracking-widest">Pagamento</span>
            </div>
            <PaymentWidget 
                :totalDue="totals.total"
                mode="settlement"
                :showFooter="false"
                @update:payments="handlePaymentsUpdate"
                @validity-change="isPaymentValid = $event"
                class="!bg-transparent !border-none"
            />
        </div>
    </div>

    <template #footer>
        <Button 
            v-if="pendingItems.length > 0"
            :label="isProcessing ? 'Processando...' : 'Confirmar Pagamento'" 
            :icon="isProcessing ? 'fa-solid fa-circle-notch fa-spin' : 'fa-solid fa-check-double'" 
            class="w-full !h-14 !text-lg !font-black !border-none shadow-xl transition-all !rounded-full"
            :class="isPaymentValid ? '!bg-green-600 hover:!bg-green-500 !text-surface-0' : '!bg-surface-3 !text-surface-500 cursor-not-allowed'"
            :disabled="!isPaymentValid || isProcessing"
            @click="confirmPayment"
        />
    </template>

  </BottomSheet>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useFormat } from '@/composables/useFormat';
import { PrinterService } from '@/services/printer-service';
import { notify } from '@/services/notify';
import api from '@/services/api';
import BottomSheet from '@/components/shared/BottomSheet.vue';
import PaymentWidget from '@/components/shared/PaymentWidget.vue';
import Button from 'primevue/button';

const props = defineProps(['visible']);
const emit = defineEmits(['update:visible', 'payment-success', 'edit-item', 'swap-item', 'delete-item']);

const sessionStore = useSessionStore();
const { formatCurrency } = useFormat();

const activeItemId = ref(null); // <--- Controle do Overlay
const currentPayments = ref([]);
const isPaymentValid = ref(false);
const isProcessing = ref(false);

const pendingItems = computed(() => sessionStore.currentAccountItems.filter(i => i.status !== 'paid' && i.status !== 'cancelled'));
const paidItems = computed(() => sessionStore.currentAccountItems.filter(i => i.status === 'paid'));

const totals = computed(() => {
    const subtotal = sessionStore.accountTotal(sessionStore.currentAccount);
    return {
        subtotal,
        total: subtotal + (sessionStore.deliveryFee || 0)
    };
});

// Abre/Fecha o Overlay ao clicar no item
const toggleOverlay = (item) => {
    const id = item.uniqueId || item.id;
    if (activeItemId.value === id) {
        activeItemId.value = null; // Fecha se já estiver aberto
    } else {
        activeItemId.value = id; // Abre este
    }
};

const triggerAction = (action, item) => {
    activeItemId.value = null; // Fecha o overlay imediatamente

    if (action === 'print') {
        printSingleItem(item);
    } else {
        emit(`${action}-item`, item);
        if (action !== 'delete') {
            emit('update:visible', false); // Fecha o sheet principal para abrir o modal de edição/troca
        }
    }
};

const printSingleItem = async (item) => {
    try {
        await PrinterService.printKitchen({
            id: sessionStore.sessionId,
            type: 'table',
            table_number: sessionStore.identifier,
            client_name: sessionStore.currentAccount,
            items: [item]
        });
        notify('success', 'Enviado', 'Item enviado para impressão.');
    } catch(e) { notify('error', 'Erro', 'Falha na impressão.'); }
};

const printAll = async () => {
    if (pendingItems.value.length === 0) return;
    try {
        await PrinterService.printAccount({
            identifier: sessionStore.identifier,
            client_name: sessionStore.currentAccount,
            sessionId: sessionStore.sessionId,
            items: pendingItems.value,
            totals: totals.value
        });
        notify('success', 'Impresso', 'Conferência enviada.');
    } catch(e) { notify('error', 'Erro', 'Falha ao imprimir.'); }
};

const handlePaymentsUpdate = (payments) => { currentPayments.value = payments; };

const confirmPayment = async () => {
    if (!isPaymentValid.value) return;
    isProcessing.value = true;
    try {
        const payload = {
            session_id: sessionStore.sessionId,
            payments: currentPayments.value,
            items: pendingItems.value,
            account: sessionStore.currentAccount
        };
        const { data } = await api.post('/pay-session', payload);
        if (data.success) {
            emit('payment-success', { total: totals.value.total, payments: currentPayments.value });
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha no pagamento.');
    } finally {
        isProcessing.value = false;
    }
};
</script>

<style scoped>
.overlay-btn {
    @apply w-10 h-10 rounded-full flex items-center justify-center transition-all shadow-sm active:scale-90 text-sm;
}
.animate-fade-in { animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>