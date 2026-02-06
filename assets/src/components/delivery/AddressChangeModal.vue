<template>
  <Drawer 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    position="bottom"
    :modal="true"
    :pt="ptOptions"
    class="!h-[85vh] md:!h-auto md:!w-[500px]" 
  >
    <template #header>
        <div class="flex flex-col w-full">
            <div class="self-center w-12 h-1.5 bg-surface-3/50 rounded-full mb-4 md:hidden"></div>
            
            <div class="flex items-center justify-between pr-4">
                <h3 class="text-lg font-black text-surface-0 flex items-center gap-2">
                    <i class="fa-solid fa-map-location-dot text-primary-500"></i>
                    {{ isModeSwitch ? 'Mudar para Entrega' : 'Meus Endereços' }}
                </h3>
                <button class="w-8 h-8 rounded-full bg-surface-2 text-surface-400 hover:text-surface-0 flex items-center justify-center transition-colors" @click="$emit('update:visible', false)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    </template>

    <div class="flex flex-col h-full bg-surface-1">
        <div class="flex-1 overflow-hidden relative">
            <SmartAddressWidget 
                :client-id="clientId"
                :mode="orderId ? 'management' : 'selection'" 
                @address-selected="handleSelection"
                @address-saved="handleSaved"
            />
        </div>
    </div>
  </Drawer>
</template>

<script setup>
import { computed } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { notify } from '@/services/notify';
import Drawer from 'primevue/drawer';
import SmartAddressWidget from '@/components/shared/SmartAddressWidget.vue';

const props = defineProps(['visible', 'orderId', 'clientId', 'isModeSwitch']);
const emit = defineEmits(['update:visible', 'success', 'address-selected']);

const deliveryStore = useDeliveryStore();

const handleSelection = async (address) => {
    // 1. Se NÃO tem orderId (ex: Carrinho), apenas emite e fecha
    if (!props.orderId && !props.isModeSwitch) {
        emit('address-selected', address);
        emit('update:visible', false);
        return;
    }

    // 2. Se TEM orderId (ex: Pedido realizado), salva no backend
    try {
        if (props.isModeSwitch) {
            const success = await deliveryStore.updateOrderAddress(props.orderId, address);
            if (success) {
                notify('success', 'Atualizado', 'Modo entrega ativado.');
                emit('success');
                emit('update:visible', false);
            }
        } else {
            const success = await deliveryStore.updateOrderAddress(props.orderId, address);
            if (success) {
                emit('success');
                emit('update:visible', false);
            }
        }
    } catch (e) {
        notify('error', 'Erro', 'Não foi possível atualizar o pedido.');
    }
};

const handleSaved = () => {
    // Opcional
};

// Estilo Premium do Drawer (Bottom Sheet)
const ptOptions = computed(() => ({
    root: { class: '!bg-surface-1 !border-t !border-surface-3/10 !rounded-t-[32px] !shadow-2xl !overflow-hidden' },
    header: { class: '!bg-surface-1 !border-b !border-surface-3/10 !px-6 !py-4 relative !z-20' },
    content: { class: '!bg-surface-1 !p-0 !h-full' },
    mask: { class: '!bg-surface-base/80 backdrop-blur-sm' },
    closeButton: { class: '!hidden' } // <--- CORREÇÃO: Esconde o botão nativo
}));
</script>