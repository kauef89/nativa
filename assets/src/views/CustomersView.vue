<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 h-24 md:h-20 px-4 md:px-6 py-4 flex flex-col md:flex-row items-start md:items-center justify-between z-20 gap-3">
        <div class="flex items-center shrink-0">
            <i class="fa-solid fa-users mr-3 text-primary-500 text-2xl md:text-xl"></i> 
            <div>
                <h1 class="text-2xl md:text-xl font-black text-surface-0">Gestão de Clientes</h1>
                <p class="text-surface-400 text-xs font-bold">CRM e Fidelidade</p>
            </div>
        </div>

        <div class="w-full md:w-96 relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-surface-400 z-10 text-sm">
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
                    input: { class: '!bg-surface-2 !border-none !pl-10 !w-full !text-surface-0 !text-sm !h-10 !rounded-full font-bold focus:!ring-2 focus:!ring-primary-500' },
                    panel: { class: '!bg-surface-2 !border-none !rounded-[16px] !shadow-2xl' }
                }"
            >
                <template #option="slotProps">
                    <div class="flex justify-between items-center p-2 rounded-lg hover:bg-surface-3 transition-colors cursor-pointer">
                        <div>
                            <div class="font-bold text-surface-0 text-sm">{{ slotProps.option.name }}</div>
                            <div class="text-xs text-surface-400 font-medium">
                                {{ slotProps.option.cpf ? 'CPF: ' + formatCPF(slotProps.option.cpf) : 'Tel: ' + formatPhone(slotProps.option.phone) }}
                            </div>
                        </div>
                        <div v-if="slotProps.option.points > 0" class="bg-primary-500/10 text-primary-400 px-2 py-0.5 rounded-full text-xs font-black whitespace-nowrap ml-4">
                            {{ slotProps.option.points }} pts
                        </div>
                    </div>
                </template>
            </AutoComplete>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 md:p-6 scrollbar-thin">
        
        <div v-if="clientData" class="grid grid-cols-1 lg:grid-cols-4 gap-6 animate-scale-in pb-10">
            
            <div class="lg:col-span-1 flex flex-col gap-4 h-fit sticky top-4">
                <div class="bg-surface-2 rounded-[24px] p-6 relative overflow-hidden shadow-lg text-center">
                    <div class="w-24 h-24 rounded-full bg-surface-3 flex items-center justify-center mx-auto mb-4 text-3xl font-black text-primary-500 shadow-inner">
                        {{ getInitials(clientData.name) }}
                    </div>
                    <h2 class="text-2xl font-black text-surface-0 leading-tight mb-1">{{ clientData.name }}</h2>
                    <p class="text-surface-400 text-sm mb-6 truncate font-medium">{{ clientData.email || 'Sem e-mail' }}</p>

                    <div class="flex flex-col gap-4 text-left border-t border-surface-3/20 pt-5">
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-black block mb-1">Telefone</label>
                            <div class="text-surface-0 font-bold text-lg flex items-center">{{ formatPhone(clientData.phone) }}</div>
                        </div>
                        <div>
                            <label class="text-[10px] text-surface-500 uppercase font-black block mb-1">CPF</label>
                            <div class="text-surface-0 font-bold text-lg">{{ formatCPF(clientData.cpf) || '--' }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-2 rounded-[24px] p-5 flex flex-col gap-4 shadow-lg">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-surface-400 text-xs font-black uppercase flex items-center">
                            <i class="fa-solid fa-map mr-2"></i> Endereços
                        </span>
                        <Button icon="fa-solid fa-plus" size="small" rounded text class="!text-primary-500 hover:!bg-surface-3 !w-8 !h-8" @click="openAddressModal()" />
                    </div>

                    <div v-if="clientData.addresses && clientData.addresses.length > 0" class="flex flex-col gap-3">
                        <div v-for="(addr, idx) in clientData.addresses" :key="idx" class="bg-surface-3/50 rounded-[16px] p-4 flex items-start gap-3 relative group hover:bg-surface-3 transition-colors">
                            <div class="mt-1 min-w-[20px] text-center"><i class="fa-solid fa-location-dot text-primary-500"></i></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-surface-0 font-bold text-sm leading-snug truncate">{{ addr.street }}, {{ addr.number }}</div>
                                <div class="text-surface-400 text-xs mt-1 truncate">{{ addr.district }}</div>
                            </div>
                            <div class="flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity absolute right-2 top-2 bg-surface-2 p-1 rounded-lg shadow-lg">
                                <Button icon="fa-solid fa-pencil" text rounded class="!w-6 !h-6 !text-surface-400 hover:!text-surface-0" @click.stop="openAddressModal(addr, idx)" />
                                <Button icon="fa-solid fa-trash" text rounded class="!w-6 !h-6 !text-surface-400 hover:!text-red-500" @click.stop="deleteAddress(addr)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 flex flex-col gap-6">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-primary-500 rounded-[24px] p-5 flex flex-col justify-between shadow-lg relative overflow-hidden group cursor-pointer hover:bg-primary-400 transition-colors">
                         <div class="relative z-10">
                            <div class="text-surface-950 text-xs font-black uppercase mb-1 flex items-center opacity-70"><i class="fa-solid fa-star mr-1.5"></i> Pontos</div>
                            <div class="text-4xl font-black text-surface-950 mb-1">{{ clientData.loyalty_points }}</div>
                            <div class="text-xs text-surface-950 font-bold opacity-80 bg-surface-base/10 inline-block px-2 py-0.5 rounded-full">Saldo Disponível</div>
                        </div>
                    </div>
                    <div class="bg-surface-2 p-5 rounded-[24px] flex flex-col justify-between shadow-lg">
                        <div class="text-surface-400 text-xs font-black uppercase mb-2">Ticket Médio</div>
                        <div class="text-2xl font-black text-surface-0">{{ formatCurrency(clientData.stats.avg_ticket) }}</div>
                    </div>
                    </div>

                <div class="bg-surface-2 rounded-[24px] p-6 min-h-[500px] shadow-lg">
                    <h3 class="text-xl font-black text-surface-0 mb-6 flex items-center">
                        <i class="fa-solid fa-clock-rotate-left mr-3 text-primary-500"></i> Histórico de Pedidos
                    </h3>

                    <div v-if="history.length > 0" class="flex flex-col gap-3">
                        <div 
                            v-for="order in history" 
                            :key="order.id" 
                            class="flex items-center justify-between p-4 rounded-[20px] bg-surface-1 hover:bg-surface-3 transition-all group cursor-pointer border border-transparent hover:border-surface-3/50"
                            @click="openOrderDetails(order)"
                        >
                            <div class="flex items-center gap-5">
                                <div class="w-12 h-12 rounded-full bg-surface-3 flex items-center justify-center text-surface-400 font-bold text-xs shadow-lg">
                                    {{ order.type }}
                                </div>
                                <div>
                                    <div class="text-surface-0 font-bold text-lg mb-0.5">Pedido #{{ order.id }}</div>
                                    <div class="text-xs text-surface-500 flex items-center font-bold">
                                        <i class="fa-regular fa-calendar-days mr-1.5"></i> {{ order.date }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-surface-0 font-black text-lg mb-0.5">{{ formatCurrency(order.total) }}</div>
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full bg-surface-3 text-surface-400">
                                    {{ order.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center h-[70vh] opacity-30 animate-pulse">
            <i class="fa-solid fa-magnifying-glass text-9xl text-surface-3 mb-6"></i>
            <p class="text-2xl text-surface-500 font-bold">Pesquise um cliente.</p>
        </div>
    </div>
    
    <Dialog v-model:visible="addressDialog.isOpen" modal header="Editar Endereço" :style="{ width: '600px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[28px]' }, header: { class: '!bg-transparent !border-b !border-surface-0/5 !text-surface-0' }, content: { class: '!bg-transparent !p-6' } }">
        <div class="flex flex-col gap-4">
            <SmartAddressForm v-model="addressDialog.data" />
            <Button label="Salvar" icon="fa-solid fa-check" class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-black !text-surface-950 !rounded-full !h-12" @click="saveAddress" :loading="addressDialog.isSaving" />
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, reactive } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import { useFormat } from '@/composables/useFormat';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import SmartAddressForm from '@/components/shared/SmartAddressForm.vue';

const { formatCurrency, formatCPF, formatPhone, formatDate, cleanDigits } = useFormat();

const selectedClient = ref(null);
const suggestions = ref([]);
const clientData = ref(null);
const history = ref([]);

const currentPage = ref(1);
const hasMore = ref(false);
const isLoadingMore = ref(false);
const loadMoreTrigger = ref(null);
let observer = null;

const addressDialog = reactive({ isOpen: false, isEditing: false, isSaving: false, editIndex: null, data: { id: null, street: '', number: '', district: '', reference: '' } });
const orderDetails = reactive({ isOpen: false, isLoading: false, data: {}, items: [], payment: {}, delivery: {}, totals: {} });

const getInitials = (name) => name ? name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() : '??';
const getRecencyColor = (days) => { if (days <= 30) return 'text-green-400'; if (days <= 60) return 'text-yellow-400'; return 'text-red-400'; };
const getStatusClass = (status) => { const s = status.toLowerCase(); if (['concluído', 'finalizado', 'entregue', 'pago'].includes(s)) return 'bg-green-500/10 text-green-400 border-green-500/20'; if (['preparando', 'pendente', 'aceito'].includes(s)) return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'; if (['cancelado'].includes(s)) return 'bg-red-500/10 text-red-400 border-red-500/20'; return 'bg-surface-800 text-surface-400 border-surface-700'; };
const getSeverity = (status) => { const s = status ? status.toLowerCase() : ''; if (['concluído', 'finalizado', 'entregue'].includes(s)) return 'success'; if (['cancelado'].includes(s)) return 'danger'; return 'warn'; };

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