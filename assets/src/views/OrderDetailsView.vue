<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between border-b border-surface-3/10 z-20">
        <div class="flex items-center gap-4">
            <Button icon="fa-solid fa-arrow-left" text rounded class="!text-surface-400 hover:!text-surface-0" @click="$router.back()" />
            <div>
                <h1 class="text-2xl font-black text-surface-0 leading-none flex items-center gap-3">
                    Pedido #{{ orderId }}
                    <span v-if="order" class="text-xs px-2 py-0.5 rounded-full uppercase tracking-wider font-black" :class="getStatusColor(order.status)">
                        {{ order.status }}
                    </span>
                </h1>
                <p class="text-xs text-surface-400 mt-1 font-bold" v-if="order">
                    {{ order.date }} • {{ order.modality === 'delivery' ? 'Entrega' : 'Retirada' }}
                </p>
            </div>
        </div>
        <div class="flex gap-2" v-if="order">
            <Button 
                label="Imprimir" 
                icon="fa-solid fa-print" 
                class="!bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !border-none !rounded-full !font-black" 
                @click="openPrintModal" 
            />
            
            <Button v-if="['new', 'preparing'].includes(order.statusSlug)" label="Editar no Caixa" icon="fa-solid fa-pen-to-square" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-black !text-surface-950 !rounded-full" @click="editInPos" />
        </div>
    </div>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
        <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary-500"></i>
    </div>

    <div v-else-if="order" class="flex-1 overflow-y-auto p-6 scrollbar-thin">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
            
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-surface-2 rounded-[24px] overflow-hidden shadow-lg">
                    <div class="p-4 border-b border-surface-3/10 flex justify-between items-center">
                        <span class="font-black text-surface-0 uppercase tracking-widest text-xs flex items-center gap-2"><i class="fa-solid fa-receipt text-primary-500"></i> Itens do Pedido</span>
                        <span class="text-xs text-surface-400 font-bold">{{ order.items.length }} itens</span>
                    </div>
                    <div class="divide-y divide-surface-3/10">
                        <div v-for="(item, idx) in order.items" :key="idx" class="p-4 flex gap-4 hover:bg-surface-3 transition-colors">
                            <div class="w-10 h-10 rounded-[12px] bg-surface-1 flex items-center justify-center font-black text-surface-300 shrink-0">{{ item.qty }}x</div>
                            <div class="flex-1 min-w-0">
                                <div class="text-surface-0 font-bold">{{ item.name }}</div>
                                <div v-if="item.modifiers && item.modifiers.length" class="text-xs text-surface-400 mt-1 space-y-0.5">
                                    <div v-for="mod in item.modifiers" :key="mod.name">+ {{ mod.name }}</div>
                                </div>
                            </div>
                            <div class="text-right font-black text-surface-0">{{ formatCurrency(item.line_total) }}</div>
                        </div>
                    </div>
                    <div class="p-4 bg-surface-2 border-t border-surface-3/10 space-y-2">
                        <div class="flex justify-between text-sm text-surface-400 font-bold"><span>Subtotal</span><span>{{ formatCurrency(order.totals.subtotal) }}</span></div>
                        <div class="flex justify-between text-sm text-surface-400 font-bold" v-if="order.totals.fee > 0"><span>Taxa</span><span>{{ formatCurrency(order.totals.fee) }}</span></div>
                        <div class="flex justify-between text-xl font-black text-surface-0 pt-2 border-t border-surface-3/10 mt-2"><span>TOTAL</span><span class="text-primary-400">{{ formatCurrency(order.totals.total) }}</span></div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                
                <div class="bg-surface-2 rounded-[24px] p-5 shadow-lg">
                    <h3 class="text-xs font-black text-surface-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-user"></i> Cliente
                    </h3>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-surface-3 flex items-center justify-center text-xl font-black text-surface-500">
                            {{ order.customer.name.charAt(0) }}
                        </div>
                        <div>
                            <div class="font-bold text-surface-0 text-lg leading-tight">{{ order.customer.name }}</div>
                            <div class="text-sm text-surface-400 font-medium">{{ order.customer.phone || 'Sem telefone' }}</div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-2" v-if="order.customer.phone">
                        <a :href="getWhatsappLink(order.customer.phone)" target="_blank" class="flex items-center justify-center gap-2 bg-green-500/10 hover:bg-green-500 text-green-500 hover:text-surface-950 py-2.5 rounded-full transition-colors text-sm font-black">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp
                        </a>
                        <a :href="'tel:+55' + cleanDigits(order.customer.phone)" class="flex items-center justify-center gap-2 bg-surface-3 hover:bg-surface-1 text-surface-0 py-2.5 rounded-full transition-colors text-sm font-black">
                            <i class="fa-solid fa-phone"></i> Ligar
                        </a>
                    </div>
                </div>
                
                <div class="bg-surface-2 rounded-[24px] p-5 shadow-lg" v-if="order.modality === 'delivery'">
                    <h3 class="text-xs font-black text-surface-400 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="fa-solid fa-map-location-dot"></i> Entrega</h3>
                    <div class="mb-4"><p class="text-surface-0 font-bold leading-relaxed">{{ order.delivery.address }}</p></div>
                </div>

            </div>
        </div>
    </div>

    <PrintSelectionModal 
        v-model:visible="printModal.isOpen"
        :order="printableOrder"
        :loading="printModal.loading"
        @confirm="handlePrintConfirm"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/session-store';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useFormat } from '@/composables/useFormat'; 
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import PrintSelectionModal from '@/components/shared/PrintSelectionModal.vue';
import { PrinterService } from '@/services/printer-service';

const route = useRoute();
const router = useRouter();
const sessionStore = useSessionStore();
const deliveryStore = useDeliveryStore();
const { formatCurrency, cleanDigits } = useFormat(); 

const orderId = route.params.id;
const order = ref(null);
const loading = ref(true);

// Estado do Modal de Impressão
const printModal = reactive({
    isOpen: false,
    loading: false
});

const fetchOrder = async () => {
    loading.value = true;
    try {
        const { data } = await api.get(`/order-details/${orderId}`);
        if(data.success) {
            const statusMapReverse = { 'Novo': 'new', 'Pendente': 'new', 'Preparando': 'preparing', 'Em Rota': 'delivering', 'Concluído': 'finished' };
            data.statusSlug = statusMapReverse[data.status] || data.status;
            order.value = data;
        }
    } catch(e) {
        notify('error', 'Erro', 'Pedido não encontrado.');
        router.push('/staff/delivery');
    } finally {
        loading.value = false;
    }
};

const getWhatsappLink = (phone) => {
    if (!phone) return '#';
    let num = cleanDigits(phone);
    if (num.length >= 10 && num.length <= 11) num = '55' + num;
    return `https://wa.me/${num}`;
};

const getStatusColor = (status) => {
    if(status === 'Novo' || status === 'new') return 'text-yellow-400 bg-yellow-500/10';
    if(status === 'Preparando' || status === 'preparing') return 'text-orange-400 bg-orange-500/10';
    return 'text-surface-400 bg-surface-3';
};

const editInPos = () => {
    if(confirm('Abrir este pedido no caixa para edição?')) {
        sessionStore.resumeSession(orderId, order.value.customer.name, true);
    }
};

// --- LÓGICA DE IMPRESSÃO ---

// Objeto normalizado para o modal de impressão
const printableOrder = computed(() => {
    if (!order.value) return null;
    
    // Tenta inferir o tipo técnico se não vier explícito
    let type = order.value.source === 'web' ? 'delivery' : (order.value.modality === 'Retirada' ? 'pickup' : 'delivery');
    
    return {
        id: order.value.id,
        type: type,
        table_number: null,
        client_name: order.value.customer.name,
        server_name: "Gestor",
        items: order.value.items // Itens já vêm com modifiers do backend
    };
});

const openPrintModal = () => {
    printModal.isOpen = true;
};

const handlePrintConfirm = async (partialOrder) => {
    printModal.loading = true;
    try {
        await PrinterService.printKitchen(partialOrder);
        notify('success', 'Impresso', 'Enviado para a cozinha.');
        printModal.isOpen = false;
    } catch (e) {
        notify('error', 'Erro', 'Falha ao imprimir.');
    } finally {
        printModal.loading = false;
    }
};

onMounted(fetchOrder);
</script>