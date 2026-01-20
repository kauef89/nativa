<template>
  <div class="flex flex-col h-full bg-surface-900 border-l border-surface-800 w-80 shrink-0">
    <div class="p-4 border-b border-surface-800">
        <h3 class="text-sm font-bold text-surface-400 uppercase tracking-widest">Atividade Recente</h3>
    </div>
    
    <div class="flex-1 overflow-y-auto p-4 space-y-4 scrollbar-thin">
        <div v-if="loading" class="text-center text-surface-500 py-4"><i class="pi pi-spinner pi-spin"></i></div>
        
        <div v-else-if="activities.length === 0" class="text-center text-surface-600 text-xs py-10">
            Nenhuma atividade recente.
        </div>

        <div v-else v-for="item in activities" :key="item.id" class="flex gap-3 animate-fade-in">
            <div class="w-2 h-full flex flex-col items-center pt-1">
                <div class="w-2 h-2 rounded-full" :class="getStatusColor(item.status)"></div>
                <div class="w-px h-full bg-surface-800 my-1"></div>
            </div>
            <div class="pb-4">
                <div class="text-xs text-surface-500 mb-0.5">{{ item.time_elapsed }}</div>
                <div class="text-sm text-white font-bold leading-tight mb-1">
                    {{ item.type === 'delivery' ? 'Pedido Delivery' : 'Mesa ' + item.table }}
                </div>
                <div class="text-xs text-surface-400">
                    {{ item.client }} <span v-if="item.total">- R$ {{ item.total }}</span>
                </div>
                <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-surface-800 border border-surface-700 text-surface-300">
                    {{ item.status }}
                </div>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store'; // Usa a mesma store

const deliveryStore = useDeliveryStore();

const loading = computed(() => deliveryStore.isLoading);

// Transforma os pedidos da store em "Atividades"
const activities = computed(() => {
    return deliveryStore.orders.map(order => ({
        id: order.id,
        type: 'delivery', // ou lógica para mesa
        client: order.client,
        status: order.status,
        total: order.total,
        time_elapsed: order.time_elapsed,
        table: null // Ajustar se vier da API de mesas
    })).slice(0, 20); // Pega os 20 últimos
});

const getStatusColor = (status) => {
    const s = status.toLowerCase();
    if(s.includes('novo') || s.includes('pendente')) return 'bg-yellow-500';
    if(s.includes('preparo')) return 'bg-orange-500';
    if(s.includes('entrega')) return 'bg-blue-500';
    if(s.includes('concluido')) return 'bg-green-500';
    return 'bg-surface-500';
};

// O fetch já é feito na DeliveryView, mas se o Feed estiver sozinho, precisa chamar:
onMounted(() => {
    if(deliveryStore.orders.length === 0) deliveryStore.fetchOrders();
});
</script>