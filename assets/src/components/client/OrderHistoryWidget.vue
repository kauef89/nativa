<template>
  <div class="flex flex-col h-full bg-surface-900">
    <div v-if="loading" class="flex-1 flex items-center justify-center">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
    </div>

    <div v-else-if="orders.length === 0" class="flex-1 flex flex-col items-center justify-center text-surface-500 opacity-60">
        <i class="fa-solid fa-receipt text-5xl mb-4"></i>
        <p class="text-sm font-bold">Você ainda não fez pedidos.</p>
        <p class="text-xs">Que tal experimentar algo hoje?</p>
    </div>

    <div v-else class="flex-1 overflow-y-auto p-4 space-y-4">
        <div v-for="order in orders" :key="order.id" class="bg-surface-800 border border-surface-700 rounded-2xl p-4 shadow-sm">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-[10px] font-black uppercase text-surface-400 tracking-wider">Pedido #{{ order.id }}</span>
                    <div class="text-xs text-surface-300 mt-0.5">{{ formatDate(order.date, true) }}</div>
                </div>
                <div :class="getStatusBadge(order.status)">
                    {{ order.status_label || order.status }}
                </div>
            </div>

            <div class="space-y-1 mb-4 border-l-2 border-surface-700 pl-3">
                <div v-for="(item, idx) in order.items_summary" :key="idx" class="text-xs text-surface-200">
                    <span class="font-bold">{{ item.qty }}x</span> {{ item.name }}
                </div>
                <div v-if="order.items_count > 3" class="text-[10px] text-surface-500 italic">
                    + {{ order.items_count - 3 }} outros itens
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-surface-700">
                <div class="text-white font-bold text-lg">
                    {{ formatCurrency(order.total) }}
                </div>
                <button 
                    class="bg-surface-900 hover:bg-primary-600 hover:text-white text-primary-400 border border-primary-500/30 px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wide transition-all active:scale-95 flex items-center gap-2"
                    @click="$emit('reorder', order.id)"
                >
                    <i class="fa-solid fa-rotate-right"></i> Pedir de Novo
                </button>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { useFormat } from '@/composables/useFormat';
import { useUserStore } from '@/stores/user-store';

const emit = defineEmits(['reorder']);
const { formatCurrency, formatDate } = useFormat();
const userStore = useUserStore();

const orders = ref([]);
const loading = ref(true);

const getStatusBadge = (status) => {
    const s = status ? status.toLowerCase() : '';
    const base = "px-2 py-1 rounded text-[10px] font-bold uppercase ";
    if (['concluído', 'entregue', 'finalizado'].includes(s)) return base + "bg-green-500/10 text-green-400 border border-green-500/20";
    if (['cancelado'].includes(s)) return base + "bg-red-500/10 text-red-400 border border-red-500/20";
    return base + "bg-yellow-500/10 text-yellow-400 border border-yellow-500/20";
};

const fetchOrders = async () => {
    if (!userStore.user?.id) {
        loading.value = false;
        return;
    }
    try {
        // Endpoint hipotético para meus pedidos. Ajuste conforme sua API.
        // Se usar /clients/{id}, lembre-se que retorna paginado.
        const { data } = await api.get('/my-orders'); 
        if (data.success) {
            orders.value = data.orders;
        }
    } catch (e) {
        console.error("Erro ao buscar histórico", e);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchOrders);
</script>