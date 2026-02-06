<template>
    <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] transition-all overflow-hidden relative">
        
        <div class="shrink-0 min-h-[80px] px-6 py-4 flex items-center justify-between z-20 bg-surface-1 relative">
            <div class="flex flex-col justify-center">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-10 h-10 rounded-full bg-surface-2 flex items-center justify-center text-primary-500 shadow-inner border border-surface-3/10">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>
                    <h1 class="text-2xl font-black text-surface-0 tracking-tight leading-none">Delivery</h1>
                </div>
                <p class="text-surface-400 text-xs font-bold pl-1">Gestão de pedidos</p>
            </div>
            
            <div class="flex gap-3">
                <Button 
                    icon="fa-solid fa-rotate" 
                    @click="refreshOrders" 
                    :loading="deliveryStore.isLoadingOrders" 
                    class="!bg-surface-2 hover:!bg-surface-3 !border-none !text-surface-400 hover:!text-surface-0 !rounded-full !w-12 !h-12 !shadow-sm transition-all active:scale-90" 
                    v-tooltip.bottom="'Atualizar'" 
                />
                
                <Button 
                    label="NOVO PEDIDO" 
                    icon="fa-solid fa-plus" 
                    class="hidden md:flex !bg-primary-600 hover:!bg-primary-500 !border-none !font-black !text-surface-950 !rounded-full !px-6 !h-12 shadow-lg shadow-primary-900/20 active:scale-95 transition-all" 
                    @click="showNewOrder = true" 
                />
                 <Button 
                    icon="fa-solid fa-plus" 
                    class="md:hidden !bg-primary-600 hover:!bg-primary-500 !border-none !text-surface-950 !rounded-full !w-12 !h-12 shadow-lg shadow-primary-900/20 active:scale-90 transition-all" 
                    @click="showNewOrder = true" 
                />
            </div>
        </div>

        <div class="md:hidden px-4 pb-4 shrink-0 z-10 flex justify-center">
            <div class="bg-surface-2 p-1.5 rounded-full flex relative shadow-inner border border-surface-3/10 w-fit">
                
                <div 
                    class="absolute top-1.5 w-10 h-10 bg-primary-500 rounded-full shadow-lg shadow-primary-500/20 transition-all duration-300 ease-[cubic-bezier(0.2,0,0,1)] z-0"
                    :style="mobileTabStyle"
                ></div>

                <button 
                    v-for="tab in tabs" :key="tab.id"
                    class="w-14 h-10 flex items-center justify-center relative z-10 transition-colors select-none group rounded-full"
                    :class="activeMobileTab === tab.id ? 'text-surface-950' : 'text-surface-400 hover:text-surface-200'"
                    @click="activeMobileTab = tab.id"
                >
                    <div class="relative flex items-center justify-center w-full h-full">
                        <i :class="[tab.icon, activeMobileTab === tab.id ? 'scale-110' : '']" class="text-lg transition-transform duration-300"></i>
                        
                        <transition name="scale">
                            <span v-if="getCount(tab.id) > 0" 
                                  class="absolute -top-1 -right-1 px-1.5 py-0.5 rounded-full text-[9px] font-black transition-colors min-w-[16px] border-2 flex items-center justify-center leading-none h-4"
                                  :class="activeMobileTab === tab.id 
                                    ? 'bg-surface-950 text-white border-surface-950' 
                                    : 'bg-primary-500 text-surface-950 border-surface-2'">
                                {{ getCount(tab.id) }}
                            </span>
                        </transition>
                    </div>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-hidden relative w-full p-4 md:p-6 bg-surface-1">
            <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-[0.02] pointer-events-none mix-blend-overlay"></div>

            <DeliveryKanban 
                :active-mobile-tab="activeMobileTab"
                @open-finalize="openFinalizeModal"
                @edit-order="editOrderInPOS"
                @cancel-order="cancelOrder"
                @change-address="openAddressModal"
                @print-order="preparePrint"
            />
        </div>

        <NewOrderWizard v-model:visible="showNewOrder" @success="refreshOrders" />
        <AddressChangeModal v-model:visible="addressDialog.isOpen" :order-id="addressDialog.order?.id" :client-id="addressDialog.order?.client_id" :is-mode-switch="addressDialog.isModeSwitch" @success="refreshOrders" />
        <FinalizeOrderModal v-model:visible="finalizeModal.isOpen" :order="finalizeModal.order" @success="refreshOrders" />
        <PrintSelectionModal v-model:visible="printModal.isOpen" :order="printModal.order" :loading="printModal.loading" @confirm="confirmPrint" />

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, reactive, computed } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useSessionStore } from '@/stores/session-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import { PrinterService } from '@/services/printer-service';
import Button from 'primevue/button';

import DeliveryKanban from '@/components/delivery/DeliveryKanban.vue';
import NewOrderWizard from '@/components/delivery/NewOrderWizard.vue';
import AddressChangeModal from '@/components/delivery/AddressChangeModal.vue';
import FinalizeOrderModal from '@/components/manager/FinalizeOrderModal.vue';
import PrintSelectionModal from '@/components/shared/PrintSelectionModal.vue';

const deliveryStore = useDeliveryStore();
const sessionStore = useSessionStore();

const activeMobileTab = ref('pending');
const showNewOrder = ref(false);
const finalizeModal = reactive({ isOpen: false, order: null });
const addressDialog = reactive({ isOpen: false, order: null, isModeSwitch: false });
const printModal = reactive({ isOpen: false, loading: false, order: null });

// 1. Adicionado Histórico
const tabs = [
    { id: 'pending', label: 'Pendentes', icon: 'fa-solid fa-bell' },
    { id: 'kitchen', label: 'Cozinha', icon: 'fa-solid fa-fire-burner' },
    { id: 'delivery', label: 'Em Rota', icon: 'fa-solid fa-motorcycle' },
    { id: 'history', label: 'Histórico', icon: 'fa-solid fa-clock-rotate-left' }
];

// 2. Lógica de Posição do Círculo (Calculada em pixels relativos à largura fixa do item)
// Cada botão tem w-14 (3.5rem = 56px). O padding do container é p-1.5 (6px).
// A bolinha tem w-10 (40px). Precisamos centralizar a bolinha de 40px dentro do slot de 56px.
// Margem esquerda = (56 - 40) / 2 = 8px.
// Offset por item = 56px.
// Left = (Index * 56px) + 8px.
const mobileTabStyle = computed(() => {
    const index = tabs.findIndex(t => t.id === activeMobileTab.value);
    const itemWidth = 56; // w-14
    const offset = 8;     // Para centralizar (56-40)/2
    
    // Usamos translate para performance
    return {
        transform: `translateX(${index * itemWidth + offset}px)`
    };
});

const getCount = (tabId) => {
    if (tabId === 'pending') return deliveryStore.pendingOrders.length;
    if (tabId === 'kitchen') return deliveryStore.kitchenOrders.length;
    if (tabId === 'delivery') return deliveryStore.deliveringOrders.length;
    return 0;
};

const refreshOrders = () => deliveryStore.fetchOrders();

const openFinalizeModal = (order) => {
    finalizeModal.order = order;
    finalizeModal.isOpen = true;
};

const editOrderInPOS = (order) => { 
    if(confirm('Abrir no Caixa para edição?')) {
        sessionStore.resumeSession(order.id, order.client, true); 
    }
};

const cancelOrder = async (order) => { 
    if(confirm('Tem certeza que deseja cancelar este pedido?')) {
        await deliveryStore.cancelOrder(order.id); 
    }
};

const openAddressModal = ({ order, isModeSwitch }) => {
    addressDialog.order = order;
    addressDialog.isModeSwitch = isModeSwitch;
    addressDialog.isOpen = true;
};

const preparePrint = async (orderSummary) => {
    printModal.loading = false;
    try {
        notify('info', 'Carregando', 'Buscando dados para impressão...');
        const { data } = await api.get(`/order-details/${orderSummary.id}`);
        if (data.success) {
            printModal.order = {
                id: data.id,
                type: data.source === 'web' ? 'delivery' : (data.modality === 'Retirada' ? 'pickup' : 'delivery'),
                table_number: null,
                client_name: data.customer.name,
                server_name: "Gestor",
                items: data.items
            };
            printModal.isOpen = true;
        }
    } catch (e) {
        notify('error', 'Erro', 'Falha ao carregar pedido.');
    }
};

const confirmPrint = async (partialOrder) => {
    printModal.loading = true;
    try {
        await PrinterService.printKitchen(partialOrder);
        notify('success', 'Enviado', 'Comando enviado para as impressoras.');
        printModal.isOpen = false;
    } catch (e) {
        notify('error', 'Erro', 'Falha na impressão.');
    } finally {
        printModal.loading = false;
    }
};

let pollingInterval = null;

onMounted(() => {
    deliveryStore.isViewingDeliveryPage = true;
    deliveryStore.stopAlertLoop();
    refreshOrders();
    pollingInterval = setInterval(refreshOrders, 15000);
});

onUnmounted(() => { 
    deliveryStore.isViewingDeliveryPage = false;
    if(pollingInterval) clearInterval(pollingInterval); 
});
</script>

<style scoped>
.scale-enter-active, .scale-leave-active { transition: all 0.2s ease; }
.scale-enter-from, .scale-leave-to { transform: scale(0); opacity: 0; }
</style>