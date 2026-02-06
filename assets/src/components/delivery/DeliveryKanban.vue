<template>
  <div class="flex h-full w-full gap-4 md:gap-6">
    
    <div 
        v-for="col in columns" 
        :key="col.id"
        class="flex-1 flex flex-col bg-surface-2/40 rounded-[32px] h-full overflow-hidden transition-all relative border border-surface-3/10 shadow-sm"
        :class="[
            {'hidden md:flex': activeMobileTab !== col.id, 'block': activeMobileTab === col.id},
            dragOverColumn === col.id ? 'bg-surface-3/60 ring-2 ring-primary-500/20' : ''
        ]"
        @dragover.prevent="onDragOver(col.id)"
        @dragleave="onDragLeave"
        @drop="onDrop($event, col.targetStatus)"
    >
        <div class="p-5 flex justify-between items-center bg-transparent sticky top-0 z-10">
            <span class="font-black text-surface-400 uppercase tracking-[0.2em] text-[10px] flex items-center gap-3">
                <div class="w-2 h-2 rounded-full shadow-[0_0_8px_rgba(var(--primary-500-rgb),0.6)]" :class="col.dotClass || 'bg-primary-500'"></div>
                {{ col.label }}
            </span>
            <span class="bg-surface-0 text-surface-950 text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                {{ col.orders.length }}
            </span>
        </div>

        <div class="flex-1 overflow-y-auto p-3 space-y-4 pb-24 md:pb-6 scrollbar-hidden">
            <div v-if="col.orders.length === 0" class="flex flex-col items-center justify-center h-48 text-surface-600 opacity-20">
                <i :class="col.emptyIcon" class="text-4xl mb-3"></i>
                <span class="text-[10px] font-black uppercase tracking-widest">{{ col.emptyLabel }}</span>
            </div>

            <div v-for="order in col.orders" :key="order.id" 
                 class="bg-surface-3 p-5 rounded-[24px] transition-all duration-300 relative overflow-hidden group cursor-pointer border border-transparent hover:border-surface-4/20 shadow-sm"
                 :class="{'ring-2 ring-primary-500/30': activeOverlayId === order.id}"
                 draggable="true"
                 @dragstart="onDragStart($event, order)"
                 @dragend="onDragEnd"
                 @click="toggleOverlay(order.id)"
            >
                <div class="pointer-events-none" :class="{'opacity-20 blur-[1px]': activeOverlayId === order.id}"> 
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-black text-primary-500 uppercase tracking-widest mb-1">#{{ order.id }}</span>
                            <span class="text-surface-0 font-bold text-base leading-tight">{{ order.client }}</span>
                        </div>
                        <div class="bg-surface-950/40 text-surface-200 text-[10px] font-bold px-3 py-1.5 rounded-full">
                            {{ col.id === 'pending' ? order.time_elapsed : formatCurrency(order.total) }}
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 mb-5">
                        <i class="fa-solid text-[10px] text-surface-500" :class="order.modality === 'delivery' ? 'fa-motorcycle' : 'fa-bag-shopping'"></i>
                        <p class="text-xs text-surface-300 leading-snug line-clamp-1 font-medium">{{ order.address }}</p>
                    </div>
                </div>
                
                <div class="flex gap-2 pt-4 border-t border-surface-0/5 pointer-events-auto relative z-10" :class="{'opacity-20 pointer-events-none': activeOverlayId === order.id}">
                    <Button 
                        :label="col.actionLabel" 
                        :icon="col.actionIcon" 
                        class="w-full !bg-primary-500 hover:!bg-primary-400 !text-surface-950 hover:!text-white !border-none !font-black !text-[11px] !h-10 !rounded-full transition-all shadow-lg shadow-primary-900/20"
                        @click.stop="advanceOrder(order.id, col.nextStatus)" 
                    />
                </div>

                <transition name="fade">
                    <div v-if="activeOverlayId === order.id" 
                         class="absolute inset-0 z-20 bg-surface-2/95 backdrop-blur-sm flex items-center justify-center animate-fade-in rounded-[24px]"
                         @click.stop
                    >
                        <div class="grid grid-cols-3 gap-4 p-4">
                            <button class="overlay-btn text-blue-400 bg-blue-500/10 hover:bg-blue-500 hover:text-white" 
                                    v-tooltip.top="'Imprimir'" 
                                    @click="triggerAction('print', order)">
                                <i class="fa-solid fa-print"></i>
                            </button>

                            <button class="overlay-btn text-orange-400 bg-orange-500/10 hover:bg-orange-500 hover:text-white" 
                                    v-tooltip.top="'Editar Pedido'" 
                                    @click="triggerAction('edit', order)">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>

                            <button class="overlay-btn text-green-400 bg-green-500/10 hover:bg-green-500 hover:text-white" 
                                    v-tooltip.top="'Endereço'" 
                                    @click="triggerAction('address', order)">
                                <i class="fa-solid fa-map-location-dot"></i>
                            </button>

                            <button class="overlay-btn text-purple-400 bg-purple-500/10 hover:bg-purple-500 hover:text-white" 
                                    v-tooltip.top="'Mudar Modo'" 
                                    @click="triggerAction('mode', order)">
                                <i class="fa-solid fa-arrow-right-arrow-left"></i>
                            </button>

                            <button class="overlay-btn text-red-400 bg-red-500/10 hover:bg-red-500 hover:text-white" 
                                    v-tooltip.top="'Cancelar'" 
                                    @click="triggerAction('cancel', order)">
                                <i class="fa-solid fa-xmark"></i>
                            </button>

                            <button class="overlay-btn text-surface-400 bg-surface-3 hover:bg-surface-4 hover:text-white" 
                                    @click="activeOverlayId = null">
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                </transition>

            </div>
        </div>
    </div>

    <div 
        class="bg-surface-2/40 rounded-[32px] overflow-hidden transition-all duration-300 ease-in-out border border-surface-3/10 shadow-sm"
        :class="[
            // Mobile: Exibe se for a aba ativa
            {'flex flex-col flex-1 h-full': activeMobileTab === 'history', 'hidden': activeMobileTab !== 'history'},
            // Desktop: Comportamento sanfona (largura fixa ou recolhida)
            {'md:flex md:flex-col md:w-80 md:min-w-[20rem]': isHistoryOpen && activeMobileTab !== 'history'},
            {'md:flex md:flex-col md:w-16 md:min-w-[4rem] md:items-center md:cursor-pointer md:hover:bg-surface-2/60': !isHistoryOpen && activeMobileTab !== 'history'}
        ]"
        @click="!isHistoryOpen && windowWidth >= 768 ? isHistoryOpen = true : null"
    >
        <div class="p-5 flex items-center justify-between bg-transparent sticky top-0 z-10 shrink-0 w-full" :class="{'!p-4 !justify-center': !isHistoryOpen && windowWidth >= 768}">
            
            <div v-if="isHistoryOpen || windowWidth < 768" class="flex items-center justify-between w-full">
                <span class="font-black text-surface-400 uppercase tracking-[0.2em] text-[10px] flex items-center gap-3">
                    <div class="w-2 h-2 rounded-full bg-surface-500 shadow-[0_0_8px_rgba(120,113,108,0.6)]"></div> 
                    Histórico
                </span>
                
                <div class="flex items-center gap-2">
                    <span class="bg-surface-3 text-surface-0 text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                        {{ historyOrders.length }}
                    </span>
                    <button 
                        v-if="windowWidth >= 768" 
                        @click.stop="isHistoryOpen = false"
                        class="text-surface-500 hover:text-primary-500 transition-colors ml-2"
                    >
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>

            <div v-else class="flex flex-col items-center gap-4 pt-2">
                <span class="bg-surface-3 text-surface-0 text-[10px] font-black w-6 h-6 rounded-full flex items-center justify-center shadow-sm">
                    {{ historyOrders.length }}
                </span>
                <div style="writing-mode: vertical-rl;" class="text-[10px] font-black text-surface-400 uppercase tracking-[0.2em] rotate-180">
                    Histórico
                </div>
                <i class="fa-solid fa-clock-rotate-left text-surface-500"></i>
            </div>
        </div>

        <div 
            v-if="isHistoryOpen || windowWidth < 768" 
            class="flex-1 overflow-y-auto p-3 space-y-3 pb-24 md:pb-6 scrollbar-hidden"
        >
            <div v-if="historyOrders.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-600 opacity-20">
                <i class="fa-solid fa-clock-rotate-left text-4xl mb-2"></i>
                <span class="text-[10px] font-black uppercase tracking-widest">Vazio</span>
            </div>

            <div 
                v-for="order in historyOrders" 
                :key="order.id"
                class="bg-surface-3 border border-transparent hover:border-surface-4/20 rounded-[24px] p-4 flex flex-col gap-3 opacity-60 hover:opacity-100 transition-all shadow-sm"
            >
                <div class="flex justify-between items-start">
                    <span class="text-xs font-bold text-surface-400">#{{ order.id }}</span>
                    <span class="text-[10px] text-surface-500">{{ order.time_elapsed }}</span>
                </div>

                <div>
                    <h3 class="font-bold text-surface-200 text-sm leading-tight">{{ order.client }}</h3>
                    <p class="text-[10px] text-surface-500 truncate mt-0.5">{{ order.address }}</p>
                </div>

                <div class="flex items-center justify-between mt-1 pt-2 border-t border-surface-0/5">
                    <span class="font-mono text-xs text-surface-300 font-bold">{{ formatCurrency(order.total) }}</span>
                    
                    <span 
                        v-if="['finished', 'closed', 'concluido', 'entregue'].includes(order.status_slug || order.status.toLowerCase())"
                        class="text-emerald-500 text-[9px] font-black uppercase flex items-center gap-1 bg-emerald-500/10 px-2 py-0.5 rounded-full"
                    >
                        <i class="fa-solid fa-check"></i> Finalizado
                    </span>

                    <span 
                        v-else
                        class="text-red-500 text-[9px] font-black uppercase flex items-center gap-1 bg-red-500/10 px-2 py-0.5 rounded-full"
                    >
                        <i class="fa-solid fa-ban"></i> Cancelado
                    </span>
                </div>
            </div>
        </div>

    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useFormat } from '@/composables/useFormat';
import api from '@/services/api';
import { notify } from '@/services/notify';
import { PrinterService } from '@/services/printer-service';
import Button from 'primevue/button';

const props = defineProps(['activeMobileTab']);
const emit = defineEmits(['open-finalize', 'edit-order', 'cancel-order', 'change-address', 'print-order']);

const router = useRouter();
const deliveryStore = useDeliveryStore();
const { formatCurrency } = useFormat();

const activeOverlayId = ref(null);
const draggedOrder = ref(null);
const dragOverColumn = ref(null);
const isHistoryOpen = ref(false); 
const windowWidth = ref(window.innerWidth);

// Monitora resize para lógica condicional de desktop/mobile
const onResize = () => { windowWidth.value = window.innerWidth; };
onMounted(() => window.addEventListener('resize', onResize));
onUnmounted(() => window.removeEventListener('resize', onResize));

// Computada para o Histórico (Finalizados + Cancelados)
const historyOrders = computed(() => {
    return deliveryStore.orders.filter(o => {
        const s = (o.status_slug || o.status).toLowerCase();
        return ['finished', 'closed', 'concluido', 'entregue', 'cancelled', 'cancelado'].includes(s);
    });
});

const columns = computed(() => [
    { 
        id: 'pending', label: 'Pendentes', 
        orders: deliveryStore.pendingOrders, 
        emptyIcon: 'fa-solid fa-bell', emptyLabel: 'Limpo',
        targetStatus: 'novo', nextStatus: 'preparando',
        actionLabel: 'Aceitar', actionIcon: 'pi pi-check',
        dotClass: 'bg-primary-500 shadow-[0_0_8px_rgba(var(--primary-500-rgb),0.6)]'
    },
    { 
        id: 'kitchen', label: 'Cozinha', 
        orders: deliveryStore.kitchenOrders, 
        emptyIcon: 'fa-solid fa-fire-burner', emptyLabel: 'Livre',
        targetStatus: 'preparando', nextStatus: 'entrega',
        actionLabel: 'Despachar', actionIcon: 'fa-solid fa-motorcycle',
        dotClass: 'bg-orange-500 shadow-[0_0_8px_rgba(249,115,22,0.6)]'
    },
    { 
        id: 'delivery', label: 'Em Rota', 
        orders: deliveryStore.deliveringOrders, 
        emptyIcon: 'fa-solid fa-route', emptyLabel: 'Sem entregas',
        targetStatus: 'entrega', nextStatus: null, 
        actionLabel: 'Finalizar', actionIcon: 'fa-solid fa-check-double',
        dotClass: 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]'
    }
]);

const toggleOverlay = (id) => {
    activeOverlayId.value = activeOverlayId.value === id ? null : id;
};

const triggerAction = (action, order) => {
    activeOverlayId.value = null; 
    
    if (action === 'print') emit('print-order', order);
    else if (action === 'edit') emit('edit-order', order);
    else if (action === 'cancel') emit('cancel-order', order);
    else if (action === 'address') emit('change-address', { order, isModeSwitch: false });
    else if (action === 'mode') emit('change-address', { order, isModeSwitch: true });
};

const advanceOrder = (id, status) => {
    if (status) {
        deliveryStore.updateOrderStatus(id, status);
        if (status === 'entrega') {
            const order = deliveryStore.kitchenOrders.find(o => o.id === id);
            if(order) printConference(order);
        }
    } else {
        const order = deliveryStore.deliveringOrders.find(o => o.id === id);
        if (order) emit('open-finalize', order);
    }
};

const printConference = async (orderSummary) => {
    try {
        notify('info', 'Imprimindo', 'Gerando relatório...');
        const { data } = await api.get(`/order-details/${orderSummary.id}`);
        if (data.success) {
            const fullOrder = {
                id: data.id,
                type: data.modality === 'Retirada' ? 'pickup' : 'delivery',
                client_name: data.customer.name,
                client_phone: data.customer.phone,
                address: data.delivery?.address,
                totals: data.totals,
                items: data.items,
                notes: data.notes,
                payments: data.payment ? [data.payment] : []
            };
            await PrinterService.printDeliveryReport(fullOrder);
        }
    } catch (e) {
        notify('error', 'Erro', 'Falha ao imprimir conferência.');
    }
};

const onDragStart = (event, order) => { draggedOrder.value = order; event.dataTransfer.effectAllowed = 'move'; };
const onDragOver = (colId) => { dragOverColumn.value = colId; };
const onDragLeave = () => { dragOverColumn.value = null; };
const onDragEnd = () => { dragOverColumn.value = null; draggedOrder.value = null; };

const onDrop = async (event, targetStatus) => {
    const order = draggedOrder.value;
    dragOverColumn.value = null;
    if (!order) return;

    const currentSlug = order.status_slug || order.status.toLowerCase();
    if (
        (targetStatus === 'novo' && ['new', 'pendente'].includes(currentSlug)) ||
        (targetStatus === 'preparando' && ['preparing', 'preparando'].includes(currentSlug)) ||
        (targetStatus === 'entrega' && ['delivering', 'entrega'].includes(currentSlug))
    ) return;

    await deliveryStore.updateOrderStatus(order.id, targetStatus);
    if (targetStatus === 'entrega') {
        printConference(order);
    }
};
</script>

<style scoped>
.scrollbar-hidden::-webkit-scrollbar { display: none; }
.scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }

.overlay-btn {
    @apply w-12 h-12 rounded-full flex items-center justify-center transition-all shadow-sm active:scale-90 text-lg;
}

.animate-fade-in { animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>