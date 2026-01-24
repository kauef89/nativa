<template>
  <div class="p-4 w-full h-full flex flex-col bg-surface-950 overflow-y-auto">
    
    <div class="max-w-[1600px] w-full mx-auto animate-fade-in">
        
        <div class="flex flex-col md:flex-row items-center justify-between mb-8 gap-6">
            <div class="flex items-center shrink-0">
                <i class="fa-solid fa-users mr-4 text-primary-500 text-4xl"></i> 
                <div>
                    <h1 class="text-3xl font-bold text-white">Gestão de Clientes</h1>
                    <p class="text-surface-400 text-sm">CRM e Fidelidade</p>
                </div>
            </div>

            <div class="w-full md:w-3/4 md:ml-auto relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 z-10 text-lg">
                    <i class="fa-solid fa-magnifying-glass"></i>
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
                                {{ clientData.phone }}
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-bold block mb-1">CPF</label>
                            <div class="text-white font-mono text-lg">{{ clientData.cpf || '--' }}</div>
                        </div>
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-bold block mb-1">Nascimento</label>
                            <div class="text-white font-mono text-lg flex items-center">
                                {{ formatDate(clientData.birth_date) || '--' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-900 border border-surface-800 rounded-2xl p-5 flex flex-col gap-4 shadow-xl">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-surface-400 text-xs font-bold uppercase flex items-center">
                            <i class="fa-solid fa-map mr-2"></i> Endereços
                        </span>
                        <Button 
                            icon="fa-solid fa-plus" 
                            size="small" 
                            rounded text 
                            class="!text-primary-500 hover:!bg-surface-800 !w-8 !h-8" 
                            v-tooltip="'Novo Endereço'" 
                            @click="openAddressModal()"
                        />
                    </div>

                    <div v-if="clientData.addresses && clientData.addresses.length > 0" class="flex flex-col gap-3">
                        <div 
                            v-for="(addr, idx) in clientData.addresses" 
                            :key="idx"
                            class="bg-surface-950/50 border border-surface-800 rounded-xl p-4 flex items-start gap-3 relative overflow-hidden group hover:border-primary-500/50 transition-colors"
                        >
                            <div class="mt-1 min-w-[20px] text-center">
                                <i class="fa-solid fa-location-dot text-primary-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-bold text-sm leading-snug truncate">{{ addr.street }}, {{ addr.number }}</div>
                                <div class="text-surface-400 text-xs mt-1 truncate">{{ addr.district }}</div>
                                <div class="text-[10px] text-surface-600 mt-2 flex items-center">
                                    <i class="fa-solid fa-clock-rotate-left text-[10px] mr-1"></i> {{ addr.source }}
                                </div>
                            </div>
                            
                            <div class="flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity absolute right-2 top-2 bg-surface-950 p-1 rounded-lg border border-surface-800 shadow-lg">
                                <Button icon="fa-solid fa-pencil" text rounded class="!w-6 !h-6 !text-surface-400 hover:!text-white" @click.stop="openAddressModal(addr, idx)" />
                                <Button icon="fa-solid fa-trash" text rounded class="!w-6 !h-6 !text-surface-400 hover:!text-red-500" @click.stop="deleteAddress(addr)" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 border-2 border-dashed border-surface-800 rounded-xl bg-surface-950/30">
                        <i class="fa-solid fa-location-dot text-2xl text-surface-600 mb-2"></i>
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
                                <i class="fa-solid fa-star mr-1.5"></i> Pontos
                            </div>
                            <div class="text-4xl font-black text-surface-950 mb-1">{{ clientData.loyalty_points }}</div>
                            <div class="text-xs text-surface-900 font-bold opacity-80 bg-black/10 inline-block px-2 py-0.5 rounded">Saldo Disponível</div>
                        </div>
                    </div>
                    <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-bold uppercase mb-2">Ticket Médio</div>
                        <div class="text-2xl font-bold text-white">R$ {{ formatMoney(clientData.stats.avg_ticket) }}</div>
                         <div class="text-xs text-green-400 mt-2 flex items-center font-medium bg-green-500/10 w-fit px-2 py-0.5 rounded">
                            <i class="fa-solid fa-chart-line mr-1"></i> Por pedido
                        </div>
                    </div>
                    <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-bold uppercase mb-2">Frequência</div>
                        <div class="text-2xl font-bold text-white">{{ clientData.stats.frequency }}</div>
                        <div class="text-xs text-primary-400 mt-2 flex items-center font-medium bg-primary-500/10 w-fit px-2 py-0.5 rounded">
                            <i class="fa-regular fa-calendar-days mr-1"></i> Histórico
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
                        <div class="text-xl font-bold text-primary-500 truncate">R$ {{ formatMoney(clientData.stats.total_spent) }}</div>
                        <div class="text-xs text-surface-500 mt-2">{{ clientData.stats.order_count }} pedidos</div>
                    </div>
                </div>

                <div class="bg-surface-900 border border-surface-800 rounded-2xl p-6 min-h-[500px] shadow-xl">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="fa-solid fa-clock-rotate-left mr-3 text-primary-500"></i> Histórico de Pedidos
                    </h3>

                    <div v-if="history.length > 0" class="flex flex-col gap-3">
                        <div 
                            v-for="order in history" 
                            :key="order.id" 
                            class="flex items-center justify-between p-4 rounded-xl border border-surface-800 bg-surface-950/30 hover:bg-surface-800 transition-all group cursor-pointer hover:border-primary-500/30"
                            @click="openOrderDetails(order)"
                        >
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 rounded-full bg-surface-800 flex items-center justify-center text-surface-400 border border-surface-700 font-bold text-xs group-hover:border-primary-500 group-hover:text-primary-500 transition-colors shadow-lg">
                                    {{ order.type }}
                                </div>
                                <div>
                                    <div class="text-white font-bold text-lg mb-0.5">Pedido #{{ order.id }}</div>
                                    <div class="text-xs text-surface-500 flex items-center">
                                        <i class="fa-regular fa-calendar-days mr-1.5"></i> {{ order.date }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-white font-bold text-lg mb-0.5">R$ {{ formatMoney(order.total) }}</div>
                                <span 
                                    class="text-[10px] uppercase font-bold px-2 py-0.5 rounded border"
                                    :class="getStatusClass(order.status)"
                                >
                                    {{ order.status }}
                                </span>
                            </div>
                        </div>

                        <div ref="loadMoreTrigger" class="py-6 text-center">
                            <i v-if="isLoadingMore" class="fa-solid fa-spinner fa-spin text-primary-500 text-2xl"></i>
                            <span v-else-if="!hasMore" class="text-surface-500 text-sm opacity-50">-- Fim do histórico --</span>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-24 text-surface-500 opacity-50">
                        <i class="fa-solid fa-inbox text-5xl mb-4 text-surface-700"></i>
                        <p class="text-lg">Nenhum pedido encontrado no histórico.</p>
                    </div>
                </div>

            </div>

        </div>

        <div v-else class="flex flex-col items-center justify-center h-[70vh] opacity-30 animate-pulse">
            <i class="fa-solid fa-magnifying-glass text-9xl text-surface-700 mb-6"></i>
            <p class="text-2xl text-surface-500 font-medium">Pesquise um cliente para ver o dossiê.</p>
        </div>

    </div>

    <Dialog 
        v-model:visible="addressDialog.isOpen" 
        modal 
        :header="addressDialog.isEditing ? 'Editar Endereço' : 'Novo Endereço'" 
        :style="{ width: '600px' }"
        :pt="{
            root: { class: '!bg-surface-900 !border !border-surface-800' },
            header: { class: '!bg-surface-900 !border-b !border-surface-800 !text-white' },
            content: { class: '!bg-surface-900 !p-6' },
            closeButton: { class: '!text-surface-400 hover:!text-white' }
        }"
    >
        <div class="flex flex-col gap-4">
            <SmartAddressForm v-model="addressDialog.data" />
            <Button 
                label="Salvar Endereço" 
                icon="fa-solid fa-check" 
                class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-bold !text-surface-900 mt-4 h-12 text-lg" 
                :loading="addressDialog.isSaving"
                @click="saveAddress"
            />
        </div>
    </Dialog>

    <Dialog 
        v-model:visible="orderDetails.isOpen" 
        modal 
        header="Detalhes do Pedido" 
        :style="{ width: '600px' }"
        :pt="{
            root: { class: '!bg-surface-900 !border !border-surface-800 !max-h-[90vh]' },
            header: { class: '!bg-surface-900 !border-b !border-surface-800 !text-white' },
            content: { class: '!bg-surface-900 !p-0' },
            closeButton: { class: '!text-surface-400 hover:!text-white' }
        }"
    >
        <div v-if="orderDetails.isLoading" class="flex justify-center p-10">
            <i class="fa-solid fa-spinner fa-spin text-3xl text-primary-500"></i>
        </div>
        
        <div v-else class="flex flex-col h-full">
            <div class="p-6 bg-surface-800 border-b border-surface-700 flex justify-between items-center">
                <div>
                    <div class="text-xl font-bold text-white">Pedido #{{ orderDetails.data.id }}</div>
                    <div class="text-sm text-surface-400">{{ orderDetails.data.date }}</div>
                </div>
                <div class="text-right">
                    <Tag :value="orderDetails.data.status" :severity="getSeverity(orderDetails.data.status)" />
                    <div class="text-xs text-surface-400 mt-1 uppercase font-bold">{{ orderDetails.data.type }}</div>
                </div>
            </div>

            <div class="p-6 space-y-6 overflow-y-auto max-h-[60vh] scrollbar-thin">
                <div>
                    <h4 class="text-xs font-bold text-surface-400 uppercase mb-3 flex items-center">
                        <i class="fa-solid fa-basket-shopping mr-2"></i> Itens
                    </h4>
                    <ul class="space-y-2">
                        <li v-for="(item, idx) in orderDetails.items" :key="idx" class="flex justify-between items-start text-sm border-b border-surface-800 pb-2 last:border-0">
                            <div>
                                <span class="font-bold text-white mr-2">{{ item.qty }}x</span>
                                <span class="text-surface-200">{{ item.name }}</span>
                                <div v-if="item.modifiers" class="pl-6 text-xs text-surface-400">
                                    <div v-for="mod in item.modifiers" :key="mod.name">+ {{ mod.name }}</div>
                                </div>
                            </div>
                            <span class="font-bold text-white">{{ formatMoney(item.line_total) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-xs font-bold text-surface-400 uppercase mb-3 flex items-center">
                            <i class="fa-solid fa-truck-fast mr-2"></i> Entrega
                        </h4>
                        <div class="bg-surface-950 p-3 rounded-xl border border-surface-800 text-sm">
                            <div class="text-white font-bold mb-1">Endereço</div>
                            <div class="text-surface-400 mb-2">{{ orderDetails.delivery.address || 'Balcão / Retirada' }}</div>
                            <div class="text-white font-bold mb-1 mt-3">Entregador</div>
                            <div class="text-surface-400 flex items-center"><i class="fa-solid fa-helmet-safety mr-2"></i> {{ orderDetails.delivery.driver || '--' }}</div>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-surface-400 uppercase mb-3 flex items-center">
                            <i class="fa-solid fa-wallet mr-2"></i> Pagamento
                        </h4>
                        <div class="bg-surface-950 p-3 rounded-xl border border-surface-800 text-sm">
                            <div class="flex justify-between mb-1"><span class="text-surface-400">Método</span><span class="text-white font-bold">{{ orderDetails.payment.method || 'Dinheiro' }}</span></div>
                            <div class="flex justify-between mb-1"><span class="text-surface-400">Taxa</span><span class="text-surface-200">{{ formatMoney(orderDetails.totals.fee) }}</span></div>
                            <div class="flex justify-between pt-2 mt-2 border-t border-surface-800"><span class="text-primary-500 font-bold">TOTAL</span><span class="text-primary-500 font-bold text-lg">{{ formatMoney(orderDetails.totals.total) }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-surface-900 border-t border-surface-800 flex justify-end">
                <Button label="Fechar" text class="!text-surface-400 hover:!text-white" @click="orderDetails.isOpen = false" />
                <Button label="Reimprimir Cupom" icon="fa-solid fa-print" class="!ml-2 !bg-surface-800 !border-surface-700 !text-white" />
            </div>
        </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, reactive } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import SmartAddressForm from '@/components/shared/SmartAddressForm.vue';

// State Principal
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

// State Endereço
const addressDialog = reactive({
    isOpen: false,
    isEditing: false,
    isSaving: false,
    editIndex: null,
    data: { id: null, street: '', number: '', district: '', reference: '' }
});

// State Detalhes
const orderDetails = reactive({ isOpen: false, isLoading: false, data: {}, items: [], payment: {}, delivery: {}, totals: {} });

// Helpers
const formatMoney = (val) => { const n = parseFloat(val); return isNaN(n) ? '0,00' : n.toFixed(2).replace('.', ','); };
const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : '??';
const formatDate = (date) => { if (!date) return null; if (date.includes('/')) return date; const [y, m, d] = date.split('-'); return `${d}/${m}/${y}`; };
const getRecencyColor = (days) => { if (days <= 30) return 'text-green-400'; if (days <= 60) return 'text-yellow-400'; return 'text-red-400'; };
const getStatusClass = (status) => { const s = status.toLowerCase(); if (['concluído', 'finalizado', 'entregue', 'pago'].includes(s)) return 'bg-green-500/10 text-green-400 border-green-500/20'; if (['preparando', 'pendente', 'aceito'].includes(s)) return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'; if (['cancelado'].includes(s)) return 'bg-red-500/10 text-red-400 border-red-500/20'; return 'bg-surface-800 text-surface-400 border-surface-700'; };
const getSeverity = (status) => { const s = status ? status.toLowerCase() : ''; if (['concluído', 'finalizado', 'entregue'].includes(s)) return 'success'; if (['cancelado'].includes(s)) return 'danger'; return 'warn'; };

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
    clientData.value = null; history.value = []; currentPage.value = 1; hasMore.value = true;
    await loadOrders(client.id);
};

const loadOrders = async (clientId) => {
    if (isLoadingMore.value) return;
    isLoadingMore.value = true;
    try {
        const response = await api.get(`/clients/${clientId}?page=${currentPage.value}&per_page=10`);
        if (response.data.success) {
            if (currentPage.value === 1) clientData.value = response.data.client;
            const newOrders = response.data.history;
            history.value.push(...newOrders);
            hasMore.value = response.data.pagination.has_more;
            if (hasMore.value) currentPage.value++;
        }
    } catch (e) { console.error(e); notify('error', 'Erro', 'Falha ao carregar dados.'); } finally { isLoadingMore.value = false; setupObserver(); }
};

const setupObserver = () => {
    if (observer) observer.disconnect();
    if (!loadMoreTrigger.value || !hasMore.value) return;
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value && !isLoadingMore.value && clientData.value) loadOrders(clientData.value.id);
    });
    observer.observe(loadMoreTrigger.value);
};

// Endereço (CRUD)
const openAddressModal = (address = null, index = null) => {
    addressDialog.isOpen = true;
    if (address) {
        addressDialog.isEditing = true; addressDialog.editIndex = index; addressDialog.data = { ...address };
    } else {
        addressDialog.isEditing = false; addressDialog.editIndex = null; addressDialog.data = { street: '', number: '', district: '', reference: '' };
    }
};

const saveAddress = async () => {
    if (!addressDialog.data.street || !addressDialog.data.number) { notify('warn', 'Atenção', 'Preencha Rua e Número.'); return; }
    addressDialog.isSaving = true;
    try {
        const response = await api.post('/clients/address', { client_id: clientData.value.id, address: addressDialog.data });
        if (response.data.success) {
            clientData.value.addresses = response.data.addresses;
            notify('success', 'Sucesso', 'Endereço salvo com sucesso.');
            addressDialog.isOpen = false;
        }
    } catch (e) { notify('error', 'Erro', 'Falha ao salvar endereço.'); } finally { addressDialog.isSaving = false; }
};

const deleteAddress = async (addr) => {
    if (!confirm('Remover este endereço?')) return;
    if (!addr.id) {
        clientData.value.addresses = clientData.value.addresses.filter(a => a !== addr);
        notify('info', 'Removido', 'Endereço removido da visualização.');
        return;
    }
    try {
        const response = await api.delete('/clients/address', { data: { client_id: clientData.value.id, address_id: addr.id } });
        if (response.data.success) {
            clientData.value.addresses = clientData.value.addresses.filter(a => a.id !== addr.id);
            notify('success', 'Removido', 'Endereço excluído.');
        }
    } catch (e) { notify('error', 'Erro', 'Falha ao excluir.'); }
};

// Detalhes
const openOrderDetails = async (order) => {
    orderDetails.data = order; orderDetails.isOpen = true; orderDetails.isLoading = true;
    try {
        const res = await api.get(`/order-details/${order.id}`);
        if (res.data.success) {
            orderDetails.items = res.data.items; orderDetails.delivery = res.data.delivery; orderDetails.payment = res.data.payment; orderDetails.totals = res.data.totals;
        }
    } catch (e) { notify('error', 'Erro', 'Não foi possível carregar detalhes.'); } finally { orderDetails.isLoading = false; }
};

watch(history, () => { setTimeout(setupObserver, 100); }, { deep: true });
onUnmounted(() => { if (observer) observer.disconnect(); });
</script>