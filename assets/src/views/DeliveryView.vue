<template>
    <div class="h-full flex flex-col p-2">
        <div class="flex justify-between items-center mb-6 px-2">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight">Gestão de Delivery</h1>
                <p class="text-surface-400 text-sm">Acompanhe os pedidos em tempo real</p>
            </div>
            <div class="flex gap-3">
                <Button icon="pi pi-refresh" rounded text @click="deliveryStore.fetchOrders()" :class="{'animate-spin': deliveryStore.isLoadingOrders}" />
                <Button label="Novo Pedido" icon="pi pi-plus" class="bg-primary-500 hover:bg-primary-400 border-none font-bold text-surface-900" />
            </div>
        </div>

        <div class="flex-1 overflow-x-auto overflow-y-hidden pb-4">
            <div class="flex gap-4 h-full min-w-[1000px]">
                
                <div class="flex-1 flex flex-col bg-surface-900/50 border border-surface-800 rounded-2xl h-full overflow-hidden">
                    <div class="p-4 border-b border-surface-800 flex justify-between items-center bg-surface-900/80 backdrop-blur">
                        <span class="font-bold text-white uppercase tracking-widest text-xs flex items-center gap-2">
                            <i class="fa-solid fa-bell text-yellow-500 animate-pulse"></i> Pendentes
                        </span>
                        <span class="bg-yellow-500/20 text-yellow-500 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deliveryStore.pendingOrders.length }}</span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 space-y-3 scrollbar-thin">
                        <div v-for="order in deliveryStore.pendingOrders" :key="order.id" class="bg-surface-800 p-4 rounded-xl border-l-4 border-yellow-500 shadow-lg group hover:bg-surface-700 transition-colors relative">
                            
                            <div class="absolute top-2 right-2">
                                <Button icon="pi pi-ellipsis-v" text rounded size="small" class="!w-8 !h-8 !text-surface-400 hover:!text-white" @click="toggleMenu($event, order)" />
                            </div>

                            <div class="flex justify-between items-start mb-2 pr-8">
                                <span class="font-black text-white text-lg">#{{ order.id }}</span>
                                <span class="text-[10px] text-surface-400 font-mono mt-1">{{ order.time_elapsed }}</span>
                            </div>
                            <div class="text-white font-bold mb-1">{{ order.client }}</div>
                            <div class="text-xs text-surface-400 mb-3 flex items-start gap-2">
                                <i class="fa-solid fa-location-dot mt-0.5 text-primary-500"></i>
                                {{ order.address }}
                            </div>
                            <div class="pt-3 border-t border-surface-700 flex gap-2">
                                <Button label="Aceitar" size="small" class="w-full !bg-green-500/20 !text-green-400 hover:!bg-green-500 hover:!text-surface-900 !border-none font-bold" @click="advanceOrder(order.id, 'preparando')" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-surface-900/50 border border-surface-800 rounded-2xl h-full overflow-hidden">
                    <div class="p-4 border-b border-surface-800 flex justify-between items-center bg-surface-900/80 backdrop-blur">
                        <span class="font-bold text-white uppercase tracking-widest text-xs flex items-center gap-2">
                            <i class="fa-solid fa-fire-burner text-orange-500"></i> Cozinha
                        </span>
                        <span class="bg-orange-500/20 text-orange-500 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deliveryStore.kitchenOrders.length }}</span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 space-y-3 scrollbar-thin">
                        <div v-for="order in deliveryStore.kitchenOrders" :key="order.id" class="bg-surface-800 p-4 rounded-xl border-l-4 border-orange-500 shadow-lg group relative">
                            
                            <div class="absolute top-2 right-2">
                                <Button icon="pi pi-ellipsis-v" text rounded size="small" class="!w-8 !h-8 !text-surface-400 hover:!text-white" @click="toggleMenu($event, order)" />
                            </div>

                            <div class="flex justify-between items-start mb-2 pr-8">
                                <span class="font-black text-white">#{{ order.id }}</span>
                                <span class="text-xs font-bold text-green-400">R$ {{ formatMoney(order.total) }}</span>
                            </div>
                            <div class="text-white font-bold mb-2">{{ order.client }}</div>
                            <div class="bg-surface-900/50 p-2 rounded border border-surface-700/50 mb-3">
                                <div class="text-[10px] text-surface-400 uppercase font-bold mb-1">Resumo</div>
                                <div class="text-xs text-surface-300 line-clamp-3">Ver detalhes...</div>
                            </div>
                            <Button label="Despachar" icon="fa-solid fa-motorcycle" size="small" class="w-full !bg-blue-500/20 !text-blue-400 hover:!bg-blue-500 hover:!text-white !border-none font-bold" @click="advanceOrder(order.id, 'entrega')" />
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-surface-900/50 border border-surface-800 rounded-2xl h-full overflow-hidden">
                    <div class="p-4 border-b border-surface-800 flex justify-between items-center bg-surface-900/80 backdrop-blur">
                        <span class="font-bold text-white uppercase tracking-widest text-xs flex items-center gap-2">
                            <i class="fa-solid fa-motorcycle text-blue-500"></i> Em Rota
                        </span>
                        <span class="bg-blue-500/20 text-blue-500 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deliveryStore.deliveringOrders.length }}</span>
                    </div>
                    <div class="flex-1 overflow-y-auto p-3 space-y-3 scrollbar-thin">
                        <div v-for="order in deliveryStore.deliveringOrders" :key="order.id" class="bg-surface-800 p-4 rounded-xl border-l-4 border-blue-500 shadow-lg relative">
                            
                            <div class="absolute top-2 right-2">
                                <Button icon="pi pi-ellipsis-v" text rounded size="small" class="!w-8 !h-8 !text-surface-400 hover:!text-white" @click="toggleMenu($event, order)" />
                            </div>

                            <div class="flex justify-between items-start mb-2 pr-8">
                                <span class="font-black text-white">#{{ order.id }}</span>
                                <a :href="'https://wa.me/55'+order.phone" target="_blank" class="text-green-400 hover:text-green-300"><i class="fa-brands fa-whatsapp"></i></a>
                            </div>
                            <div class="text-white font-bold mb-1">{{ order.client }}</div>
                            <div class="text-xs text-surface-400 mb-3">{{ order.address }}</div>
                            <Button label="Concluir" icon="fa-solid fa-check" size="small" class="w-full !bg-surface-700 !text-surface-300 hover:!bg-green-500 hover:!text-white !border-none font-bold" @click="advanceOrder(order.id, 'concluido')" />
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <Menu ref="menu" :model="menuItems" :popup="true" class="!bg-surface-900 !border-surface-700" />

        <Dialog v-model:visible="addressDialog.isOpen" modal header="Alterar Endereço" :style="{ width: '500px' }" 
                :pt="{ root: { class: '!bg-surface-900 !border-surface-700' }, header: { class: '!bg-surface-950 !text-white' }, content: { class: '!bg-surface-900' } }">
            
            <div v-if="addressDialog.loading" class="flex justify-center p-8">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-primary-500"></i>
            </div>

            <div v-else>
                <div v-if="!addressDialog.showNewForm" class="flex flex-col gap-2">
                    <p class="text-xs font-bold text-surface-500 uppercase mb-2">Endereços Salvos do Cliente</p>
                    
                    <div v-if="clientAddresses.length === 0" class="text-center py-4 text-surface-500 text-sm italic">
                        Nenhum endereço salvo encontrado.
                    </div>

                    <div v-for="(addr, idx) in clientAddresses" :key="idx" 
                         class="p-3 rounded-xl border border-surface-700 bg-surface-950/50 hover:bg-surface-800 cursor-pointer flex items-center gap-3 transition-colors"
                         @click="confirmAddressChange(addr)"
                    >
                        <i class="fa-solid fa-location-dot text-surface-400"></i>
                        <div class="flex-1">
                            <div class="text-sm font-bold text-white">{{ addr.street }}, {{ addr.number }}</div>
                            <div class="text-xs text-surface-400">{{ addr.district }}</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-surface-600"></i>
                    </div>

                    <Button label="Novo Endereço" icon="fa-solid fa-plus" class="mt-4 w-full !bg-surface-800 !border-surface-700 !text-white" @click="addressDialog.showNewForm = true" />
                </div>

                <div v-else class="flex flex-col gap-4">
                    <SmartAddressForm v-model="addressDialog.newAddress" />
                    
                    <div class="flex justify-end gap-2 mt-2">
                        <Button label="Voltar" text class="!text-surface-400 hover:!text-white" @click="addressDialog.showNewForm = false" />
                        <Button label="Salvar Alteração" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none" @click="confirmAddressChange(addressDialog.newAddress)" />
                    </div>
                </div>
            </div>
        </Dialog>

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, reactive } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useSessionStore } from '@/stores/session-store';
import { useRouter } from 'vue-router'; // Para redirecionar ao PDV
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import Dialog from 'primevue/dialog';
import SmartAddressForm from '@/components/SmartAddressForm.vue';

const deliveryStore = useDeliveryStore();
const sessionStore = useSessionStore();
const router = useRouter();

let pollingInterval = null;
const menu = ref(null);
const selectedOrder = ref(null);
const clientAddresses = ref([]);

// Estado do Modal de Endereço
const addressDialog = reactive({
    isOpen: false,
    loading: false,
    showNewForm: false,
    newAddress: { street: '', number: '', district: '', reference: '' }
});

// Itens do Menu Contextual
const menuItems = ref([
    {
        label: 'Adicionar Itens',
        icon: 'pi pi-plus',
        command: () => editOrderInPOS()
    },
    {
        label: 'Alterar Endereço',
        icon: 'pi pi-map-marker',
        command: () => openAddressModal()
    },
    {
        separator: true
    },
    {
        label: 'Cancelar Pedido',
        icon: 'pi pi-times',
        class: 'text-red-500',
        command: () => cancelOrder()
    }
]);

// Funções de Menu
const toggleMenu = (event, order) => {
    selectedOrder.value = order;
    menu.value.toggle(event);
};

// 1. AÇÃO: Editar no POS
const editOrderInPOS = () => {
    // Como o POS funciona com Sessões, precisamos simular uma sessão
    // TODO: No futuro, criar endpoint para "converter" pedido em sessão editável
    // Por enquanto, vamos apenas avisar ou redirecionar com aviso
    
    if (confirm('Deseja abrir este pedido no Caixa para adicionar itens?')) {
        // Logica temporária: Abre uma sessão de balcão com o nome do cliente
        sessionStore.openSession('counter', selectedOrder.value.client);
        // O ideal seria carregar os itens existentes, mas isso requer refatoração profunda do POSController
    }
};

// 2. AÇÃO: Cancelar
const cancelOrder = async () => {
    if (!confirm(`Tem certeza que deseja cancelar o pedido #${selectedOrder.value.id}?`)) return;
    await deliveryStore.cancelOrder(selectedOrder.value.id);
};

// 3. AÇÃO: Alterar Endereço
const openAddressModal = async () => {
    addressDialog.isOpen = true;
    addressDialog.loading = true;
    addressDialog.showNewForm = false;
    clientAddresses.value = [];

    // Tenta buscar os endereços do cliente através do ID do pedido
    try {
        // Primeiro, precisamos saber quem é o cliente do pedido.
        // O objeto 'order' na lista é resumido. Vamos pegar os detalhes.
        const resDetail = await api.get(`/order-details/${selectedOrder.value.id}`);
        if(resDetail.data.success) {
            // Agora buscamos endereços salvos deste cliente (usando endpoint de busca ou detalhes)
            // Se o user_id não vier no detalhe, usamos o endpoint de busca de cliente pelo telefone
            const phone = resDetail.data.customer.phone;
            
            if(phone) {
                const resSearch = await api.get(`/search-clients?q=${phone}`);
                if(resSearch.data.success && resSearch.data.clients.length > 0) {
                    const clientId = resSearch.data.clients[0].id;
                    const resClient = await api.get(`/clients/${clientId}`);
                    if(resClient.data.success) {
                        clientAddresses.value = resClient.data.client.addresses || [];
                    }
                }
            }
        }
    } catch (e) {
        console.error('Erro ao buscar endereços', e);
        // Se falhar, apenas libera para criar novo
        addressDialog.showNewForm = true;
    } finally {
        addressDialog.loading = false;
    }
};

const confirmAddressChange = async (addr) => {
    if (!addr.street || !addr.number) {
        notify('warn', 'Atenção', 'Endereço incompleto.');
        return;
    }
    
    addressDialog.loading = true;
    const success = await deliveryStore.updateOrderAddress(selectedOrder.value.id, addr);
    addressDialog.loading = false;
    
    if (success) {
        addressDialog.isOpen = false;
    }
};

const advanceOrder = (id, status) => {
    deliveryStore.updateOrderStatus(id, status);
};

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');

onMounted(() => {
    deliveryStore.fetchOrders();
    pollingInterval = setInterval(() => {
        deliveryStore.fetchOrders();
    }, 15000);
});

onUnmounted(() => {
    if(pollingInterval) clearInterval(pollingInterval);
});
</script>