<template>
  <div class="p-4 w-full h-full flex flex-col bg-surface-950 overflow-y-auto">
    
    <div class="max-w-[1600px] w-full mx-auto animate-fade-in">
        
        <div class="flex flex-col md:flex-row items-center justify-between mb-8 gap-6">
            <div class="flex items-center shrink-0">
                <i class="pi pi-users mr-4 text-primary-500 text-4xl"></i> 
                <div>
                    <h1 class="text-3xl font-bold text-white">Gestão de Clientes</h1>
                    <p class="text-surface-400 text-sm">CRM e Fidelidade</p>
                </div>
            </div>

            <div class="w-full md:flex-1 md:max-w-[800px] md:ml-auto relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 z-10 text-lg">
                    <i class="pi pi-search"></i>
                </span>
                <AutoComplete 
                    v-model="selectedClient" 
                    :suggestions="suggestions" 
                    optionLabel="label" 
                    placeholder="Buscar por Nome, CPF ou Telefone..." 
                    class="w-full"
                    @complete="searchClient"
                    @item-select="onClientSelect"
                    :pt="{
                        input: { class: '!bg-surface-900 !border-surface-700 !pl-12 !w-full !text-white !text-lg !h-14 !rounded-xl focus:!border-primary-500 transition-colors shadow-lg' },
                        panel: { class: '!bg-surface-900 !border-surface-700' }
                    }"
                >
                    <template #option="slotProps">
                        <div class="flex justify-between items-center p-3 border-b border-surface-800 last:border-0 hover:bg-surface-800 transition-colors cursor-pointer">
                            <div>
                                <div class="font-bold text-white text-lg">{{ slotProps.option.name }}</div>
                                <div class="text-sm text-surface-400">
                                    {{ slotProps.option.cpf ? 'CPF: ' + slotProps.option.cpf : 'Tel: ' + slotProps.option.phone }}
                                </div>
                            </div>
                            <div v-if="slotProps.option.points > 0" class="bg-primary-500/10 text-primary-400 px-3 py-1 rounded-lg text-sm font-bold whitespace-nowrap ml-4">
                                {{ slotProps.option.points }} pts
                            </div>
                        </div>
                    </template>
                </AutoComplete>
            </div>
        </div>

        <div v-if="clientData" class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-scale-in pb-10">
            
            <div class="lg:col-span-1 flex flex-col gap-4 h-fit sticky top-4">
                
                <div class="bg-surface-900 border border-surface-800 rounded-2xl p-6 relative overflow-hidden shadow-xl text-center">
                    <div class="w-24 h-24 rounded-full bg-surface-800 border-4 border-surface-700 flex items-center justify-center mx-auto mb-4 text-3xl font-bold text-primary-500 shadow-inner">
                        {{ getInitials(clientData.name) }}
                    </div>
                    <h2 class="text-2xl font-bold text-white leading-tight mb-1">{{ clientData.name }}</h2>
                    <p class="text-surface-400 text-sm mb-6 truncate">{{ clientData.email || 'Sem e-mail' }}</p>

                    <div class="flex flex-col gap-4 text-left border-t border-surface-800 pt-5">
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-bold block mb-1">Telefone</label>
                            <div class="text-white font-mono text-lg flex items-center">
                                <i class="pi pi-whatsapp mr-2 text-green-500"></i> {{ clientData.phone }}
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-bold block mb-1">CPF</label>
                            <div class="text-white font-mono text-lg">{{ clientData.cpf || '--' }}</div>
                        </div>
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-bold block mb-1">Nascimento</label>
                            <div class="text-white text-lg">{{ formatDate(clientData.birth_date) || '--' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-900 border border-surface-800 rounded-2xl p-5 flex flex-col gap-4 shadow-xl">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-surface-400 text-xs font-bold uppercase flex items-center">
                            <i class="pi pi-map mr-2"></i> Endereços
                        </span>
                        <Button icon="pi pi-plus" size="small" rounded text class="!text-primary-500 hover:!bg-surface-800 !w-8 !h-8" v-tooltip="'Adicionar'" />
                    </div>

                    <div v-if="clientData.addresses && clientData.addresses.length > 0" class="flex flex-col gap-3">
                        <div 
                            v-for="(addr, idx) in clientData.addresses" 
                            :key="idx"
                            class="bg-surface-950/50 border border-surface-800 rounded-xl p-4 flex items-start gap-3 relative overflow-hidden group hover:border-primary-500/50 transition-colors cursor-pointer"
                        >
                            <div class="mt-1 min-w-[20px] text-center">
                                <i class="pi pi-map-marker text-primary-500"></i>
                            </div>
                            <div>
                                <div class="text-white font-bold text-sm leading-snug">{{ addr.street }}, {{ addr.number }}</div>
                                <div class="text-surface-400 text-xs mt-1">{{ addr.district }}</div>
                                <div class="text-[10px] text-surface-600 mt-2 flex items-center">
                                    <i class="pi pi-history text-[10px] mr-1"></i> {{ addr.source }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 border-2 border-dashed border-surface-800 rounded-xl bg-surface-950/30">
                        <i class="pi pi-map-marker text-2xl text-surface-600 mb-2"></i>
                        <p class="text-surface-500 text-sm">Sem endereços.</p>
                    </div>
                </div>

            </div>

            <div class="lg:col-span-3 flex flex-col gap-6">
                
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    
                    <div class="bg-primary-500 rounded-2xl p-5 flex flex-col justify-between shadow-lg relative overflow-hidden group cursor-pointer hover:bg-primary-400 transition-colors">
                        <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/20 rounded-full blur-2xl"></div>
                        
                        <div class="relative z-10">
                            <div class="text-surface-900 text-xs font-black uppercase mb-1 flex items-center opacity-70">
                                <i class="pi pi-star-fill mr-1.5"></i> Pontos
                            </div>
                            <div class="text-4xl font-black text-surface-950 mb-1">{{ clientData.loyalty_points }}</div>
                            <div class="text-xs text-surface-900 font-bold opacity-80 bg-black/10 inline-block px-2 py-0.5 rounded">
                                Saldo Disponível
                            </div>
                        </div>
                    </div>

                    <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-bold uppercase mb-2">Ticket Médio</div>
                        <div class="text-2xl font-bold text-white">R$ {{ formatMoney(clientData.stats.avg_ticket) }}</div>
                        <div class="text-xs text-green-400 mt-2 flex items-center font-medium bg-green-500/10 w-fit px-2 py-0.5 rounded">
                            <i class="pi pi-chart-line mr-1"></i> Por pedido
                        </div>
                    </div>

                    <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-bold uppercase mb-2">Frequência</div>
                        <div class="text-2xl font-bold text-white">{{ clientData.stats.frequency }}</div>
                        <div class="text-xs text-primary-400 mt-2 flex items-center font-medium bg-primary-500/10 w-fit px-2 py-0.5 rounded">
                            <i class="pi pi-calendar mr-1"></i> Histórico
                        </div>
                    </div>

                    <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-bold uppercase mb-2">Última Compra</div>
                        <div class="text-2xl font-bold" :class="getRecencyColor(clientData.stats.last_buy_days)">
                            {{ clientData.stats.last_buy_days }} dias
                        </div>
                        <div class="text-xs text-surface-500 mt-2">atrás</div>
                    </div>

                    <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-bold uppercase mb-2">Total Gasto</div>
                        <div class="text-xl font-bold text-primary-500 truncate" :title="'R$ ' + formatMoney(clientData.stats.total_spent)">
                            R$ {{ formatMoney(clientData.stats.total_spent) }}
                        </div>
                        <div class="text-xs text-surface-500 mt-2">{{ clientData.stats.order_count }} pedidos</div>
                    </div>
                </div>

                <div class="bg-surface-900 border border-surface-800 rounded-2xl p-6 min-h-[500px] shadow-xl">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="pi pi-history mr-3 text-primary-500"></i> Histórico de Pedidos
                    </h3>

                    <div v-if="history.length > 0" class="flex flex-col gap-3">
                        <div 
                            v-for="order in history" 
                            :key="order.id" 
                            class="flex items-center justify-between p-4 rounded-xl border border-surface-800 bg-surface-950/30 hover:bg-surface-800 transition-all group cursor-pointer hover:border-primary-500/30"
                        >
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 rounded-full bg-surface-800 flex items-center justify-center text-surface-400 border border-surface-700 font-bold text-xs group-hover:border-primary-500 group-hover:text-primary-500 transition-colors shadow-lg">
                                    {{ order.type }}
                                </div>
                                <div>
                                    <div class="text-white font-bold text-lg mb-0.5">Pedido #{{ order.id }}</div>
                                    <div class="text-xs text-surface-500 flex items-center">
                                        <i class="pi pi-calendar mr-1.5"></i> {{ order.date }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-white font-bold text-lg mb-0.5">R$ {{ formatMoney(order.total) }}</div>
                                <span 
                                    class="text-[10px] uppercase font-bold px-2 py-0.5 rounded border"
                                    :class="{
                                        'bg-green-500/10 text-green-400 border-green-500/20': ['Concluído', 'Finalizado', 'Entregue'].includes(order.status),
                                        'bg-yellow-500/10 text-yellow-400 border-yellow-500/20': ['Preparando', 'Pendente'].includes(order.status),
                                        'bg-red-500/10 text-red-400 border-red-500/20': order.status === 'Cancelado'
                                    }"
                                >
                                    {{ order.status }}
                                </span>
                            </div>
                        </div>

                        <div ref="loadMoreTrigger" class="py-6 text-center">
                            <i v-if="isLoadingMore" class="pi pi-spin pi-spinner text-primary-500 text-2xl"></i>
                            <span v-else-if="!hasMore" class="text-surface-500 text-sm opacity-50">-- Fim do histórico --</span>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-24 text-surface-500 opacity-50">
                        <i class="pi pi-inbox text-5xl mb-4 text-surface-700"></i>
                        <p class="text-lg">Nenhum pedido encontrado no histórico.</p>
                    </div>
                </div>

            </div>

        </div>

        <div v-else class="flex flex-col items-center justify-center h-[70vh] opacity-30 animate-pulse">
            <i class="pi pi-search text-9xl text-surface-700 mb-6"></i>
            <p class="text-2xl text-surface-500 font-medium">Pesquise um cliente para ver o dossiê.</p>
        </div>

    </div>
  </div>
</template>

<script setup>
// ... (Script mantém igual ao anterior, a lógica já estava ok)
import { ref, onMounted, onUnmounted, watch } from 'vue';
import api from '@/services/api';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import { notify } from '@/services/notify';

// State
const selectedClient = ref(null);
const suggestions = ref([]);
const clientData = ref(null);
const history = ref([]);

// Pagination State
const currentPage = ref(1);
const hasMore = ref(false);
const isLoadingMore = ref(false);
const loadMoreTrigger = ref(null);
let observer = null;

// Helpers
const formatMoney = (val) => val ? val.toFixed(2).replace('.', ',') : '0,00';
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : '??';
const formatDate = (date) => {
    if (!date) return null;
    if (date.includes('/')) return date; 
    const [y, m, d] = date.split('-');
    return `${d}/${m}/${y}`;
};
const getRecencyColor = (days) => {
    if (days <= 30) return 'text-green-400';
    if (days <= 60) return 'text-yellow-400';
    return 'text-red-400';
};

// API Actions
const searchClient = async (event) => {
    try {
        const response = await api.get(`/search-clients?q=${event.query}`);
        if (response.data.success) suggestions.value = response.data.clients;
    } catch (e) { console.error(e); }
};

const onClientSelect = async (event) => {
    const client = event.value;
    selectedClient.value = client.name;
    
    if (!client || !client.id) return;

    clientData.value = null;
    history.value = [];
    currentPage.value = 1;
    hasMore.value = true;

    await loadOrders(client.id);
};

const loadOrders = async (clientId) => {
    if (isLoadingMore.value) return;
    isLoadingMore.value = true;

    try {
        const response = await api.get(`/clients/${clientId}?page=${currentPage.value}&per_page=10`);
        if (response.data.success) {
            if (currentPage.value === 1) {
                clientData.value = response.data.client;
            }
            
            const newOrders = response.data.history;
            history.value.push(...newOrders);
            
            hasMore.value = response.data.pagination.has_more;
            if (hasMore.value) currentPage.value++;
        }
    } catch (e) {
        console.error(e);
        notify('error', 'Erro', 'Falha ao carregar dados.');
    } finally {
        isLoadingMore.value = false;
        setupObserver(); 
    }
};

const setupObserver = () => {
    if (observer) observer.disconnect();
    if (!loadMoreTrigger.value || !hasMore.value) return;

    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value && !isLoadingMore.value && clientData.value) {
            loadOrders(clientData.value.id);
        }
    });
    observer.observe(loadMoreTrigger.value);
};

watch(history, () => {
    setTimeout(setupObserver, 100); 
}, { deep: true });

onUnmounted(() => {
    if (observer) observer.disconnect();
});
</script>

<style scoped>
.animate-scale-in { animation: scaleIn 0.3s ease-out; }
@keyframes scaleIn { from { opacity: 0; transform: scale(0.98); } to { opacity: 1; transform: scale(1); } }
</style>