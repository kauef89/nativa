<template>
    <div class="h-full flex flex-col bg-surface-950 relative overflow-hidden">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-4 md:p-6 pb-2 md:pb-6 gap-4 shrink-0 z-20 bg-surface-950">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-motorcycle text-primary-500"></i>
                    <span>Delivery</span>
                </h1>
                <p class="text-surface-400 text-xs md:text-sm font-medium">Gestão de pedidos em tempo real</p>
            </div>
            
            <div class="flex gap-2 w-full md:w-auto">
                <Button icon="pi pi-refresh" rounded text @click="deliveryStore.fetchOrders()" :class="{'animate-spin': deliveryStore.isLoadingOrders}" />
                <Button label="Novo" icon="pi pi-plus" class="flex-1 md:flex-none !bg-primary-500 hover:!bg-primary-400 !border-none !font-bold !text-surface-900 !rounded-xl" />
            </div>
        </div>

        <div class="md:hidden px-4 pb-2 shrink-0">
            <div class="bg-surface-900 p-1 rounded-xl flex border border-surface-800 relative">
                <button 
                    v-for="tab in tabs" 
                    :key="tab.id"
                    class="flex-1 py-2 rounded-lg text-xs font-bold transition-all duration-300 flex items-center justify-center gap-2 relative z-10"
                    :class="activeMobileTab === tab.id ? 'bg-primary-500 text-surface-900 shadow-md' : 'text-surface-400 hover:text-white'"
                    @click="activeMobileTab = tab.id"
                >
                    <i :class="tab.icon"></i>
                    <span>{{ tab.label }}</span>
                    <span v-if="getCount(tab.id) > 0" class="ml-1 text-[9px] px-1.5 rounded-full" 
                          :class="activeMobileTab === tab.id ? 'bg-surface-900 text-primary-500' : 'bg-surface-800 text-surface-300'">
                        {{ getCount(tab.id) }}
                    </span>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-hidden relative w-full md:p-6 md:pt-0">
            
            <div class="flex h-full w-full gap-6">
                
                <div class="flex-1 flex flex-col bg-surface-900/30 md:bg-surface-900 md:border md:border-surface-800 md:rounded-2xl h-full overflow-hidden transition-all"
                     :class="{'hidden md:flex': activeMobileTab !== 'pending', 'block': activeMobileTab === 'pending'}">
                    
                    <div class="p-3 md:p-4 border-b border-surface-800 flex justify-between items-center bg-surface-950/90 backdrop-blur sticky top-0 z-10 mx-4 md:mx-0 rounded-xl md:rounded-t-2xl md:rounded-b-none mt-2 md:mt-0 border-x md:border-x-0 border-t md:border-t-0 shadow-sm md:shadow-none">
                        <span class="font-bold text-white uppercase tracking-widest text-xs flex items-center gap-2">
                            <i class="fa-solid fa-bell text-yellow-500 animate-pulse"></i> Pendentes
                        </span>
                        <span class="bg-yellow-500/20 text-yellow-500 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deliveryStore.pendingOrders.length }}</span>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin pb-24 md:pb-4">
                        <div v-if="deliveryStore.pendingOrders.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 opacity-50">
                            <i class="fa-solid fa-check-circle text-4xl mb-3"></i>
                            <span class="text-sm font-medium">Tudo limpo!</span>
                        </div>
                        
                        <div v-for="order in deliveryStore.pendingOrders" :key="order.id" 
                             class="bg-surface-800 p-4 rounded-2xl border border-surface-700 shadow-sm group relative overflow-hidden active:scale-[0.98] transition-transform duration-200">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-yellow-500"></div>
                            
                            <div class="pl-2">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold text-surface-400 uppercase tracking-wider">Pedido #{{ order.id }}</span>
                                        <span class="text-white font-bold text-base leading-tight">{{ order.client }}</span>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold bg-surface-950 text-surface-300 px-2 py-1 rounded border border-surface-700">
                                        {{ order.time_elapsed }}
                                    </span>
                                </div>

                                <div class="flex items-start gap-2 mb-3">
                                    <i class="fa-solid fa-location-dot text-primary-500 text-xs mt-0.5"></i>
                                    <p class="text-xs text-surface-300 leading-snug line-clamp-2">{{ order.address }}</p>
                                </div>

                                <div class="flex gap-2 mt-3 pt-3 border-t border-surface-700/50">
                                    <Button label="Aceitar" icon="pi pi-check" size="small" class="flex-1 !bg-green-500/10 !text-green-400 hover:!bg-green-500 hover:!text-surface-900 !border-none font-bold !text-xs !h-9" @click="advanceOrder(order.id, 'preparando')" />
                                    <Button icon="pi pi-ellipsis-v" text rounded size="small" class="!w-9 !h-9 !text-surface-400 hover:!text-white hover:!bg-surface-700" @click="toggleMenu($event, order)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-surface-900/30 md:bg-surface-900 md:border md:border-surface-800 md:rounded-2xl h-full overflow-hidden transition-all"
                     :class="{'hidden md:flex': activeMobileTab !== 'kitchen', 'block': activeMobileTab === 'kitchen'}">
                    
                    <div class="p-3 md:p-4 border-b border-surface-800 flex justify-between items-center bg-surface-950/90 backdrop-blur sticky top-0 z-10 mx-4 md:mx-0 rounded-xl md:rounded-t-2xl md:rounded-b-none mt-2 md:mt-0 border-x md:border-x-0 border-t md:border-t-0 shadow-sm md:shadow-none">
                        <span class="font-bold text-white uppercase tracking-widest text-xs flex items-center gap-2">
                            <i class="fa-solid fa-fire-burner text-orange-500"></i> Cozinha
                        </span>
                        <span class="bg-orange-500/20 text-orange-500 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deliveryStore.kitchenOrders.length }}</span>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin pb-24 md:pb-4">
                        <div v-if="deliveryStore.kitchenOrders.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 opacity-50">
                            <i class="fa-solid fa-utensils text-4xl mb-3"></i>
                            <span class="text-sm font-medium">Cozinha livre.</span>
                        </div>

                        <div v-for="order in deliveryStore.kitchenOrders" :key="order.id" 
                             class="bg-surface-800 p-4 rounded-2xl border border-surface-700 shadow-sm group relative overflow-hidden">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-orange-500"></div>
                            
                            <div class="pl-2">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold text-surface-400 uppercase tracking-wider">#{{ order.id }}</span>
                                        <span class="text-white font-bold text-base">{{ order.client }}</span>
                                    </div>
                                    <span class="text-xs font-bold text-green-400 bg-green-500/10 px-2 py-1 rounded">R$ {{ formatMoney(order.total) }}</span>
                                </div>

                                <div class="bg-surface-950/50 p-2.5 rounded-lg border border-surface-700/50 mb-3">
                                    <div class="text-[10px] text-surface-500 uppercase font-bold mb-1 flex items-center gap-1">
                                        <i class="fa-solid fa-list-ul"></i> Resumo
                                    </div>
                                    <div class="text-xs text-surface-300 line-clamp-3 leading-relaxed opacity-80">
                                        Clique para ver detalhes...
                                    </div>
                                </div>

                                <div class="flex gap-2 pt-2 border-t border-surface-700/50">
                                    <Button label="Despachar" icon="fa-solid fa-motorcycle" size="small" class="flex-1 !bg-blue-500/10 !text-blue-400 hover:!bg-blue-500 hover:!text-white !border-none font-bold !text-xs !h-9" @click="advanceOrder(order.id, 'entrega')" />
                                    <Button icon="pi pi-ellipsis-v" text rounded size="small" class="!w-9 !h-9 !text-surface-400 hover:!text-white hover:!bg-surface-700" @click="toggleMenu($event, order)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-1 flex flex-col bg-surface-900/30 md:bg-surface-900 md:border md:border-surface-800 md:rounded-2xl h-full overflow-hidden transition-all"
                     :class="{'hidden md:flex': activeMobileTab !== 'delivery', 'block': activeMobileTab === 'delivery'}">
                    
                    <div class="p-3 md:p-4 border-b border-surface-800 flex justify-between items-center bg-surface-950/90 backdrop-blur sticky top-0 z-10 mx-4 md:mx-0 rounded-xl md:rounded-t-2xl md:rounded-b-none mt-2 md:mt-0 border-x md:border-x-0 border-t md:border-t-0 shadow-sm md:shadow-none">
                        <span class="font-bold text-white uppercase tracking-widest text-xs flex items-center gap-2">
                            <i class="fa-solid fa-road text-blue-500"></i> Em Rota
                        </span>
                        <span class="bg-blue-500/20 text-blue-500 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ deliveryStore.deliveringOrders.length }}</span>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin pb-24 md:pb-4">
                        <div v-if="deliveryStore.deliveringOrders.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 opacity-50">
                            <i class="fa-solid fa-helmet-safety text-4xl mb-3"></i>
                            <span class="text-sm font-medium">Nenhuma entrega.</span>
                        </div>

                        <div v-for="order in deliveryStore.deliveringOrders" :key="order.id" 
                             class="bg-surface-800 p-4 rounded-2xl border border-surface-700 shadow-sm group relative overflow-hidden">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>
                            
                            <div class="pl-2">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold text-surface-400 uppercase tracking-wider">#{{ order.id }}</span>
                                        <span class="text-white font-bold text-base">{{ order.client }}</span>
                                    </div>
                                    <a :href="'https://wa.me/55'+order.phone" target="_blank" class="w-8 h-8 rounded-lg bg-green-500/10 text-green-400 border border-green-500/20 flex items-center justify-center hover:bg-green-500 hover:text-white transition-colors">
                                        <i class="fa-brands fa-whatsapp text-lg"></i>
                                    </a>
                                </div>

                                <div class="text-xs text-surface-300 mb-3 truncate flex items-center gap-2 opacity-80">
                                    <i class="fa-solid fa-map-pin text-blue-400"></i> {{ order.address }}
                                </div>

                                <div class="flex gap-2 pt-2 border-t border-surface-700/50">
                                    <Button label="Concluir" icon="pi pi-check" size="small" class="flex-1 !bg-surface-700 !text-surface-300 hover:!bg-green-500 hover:!text-white !border-none font-bold !text-xs !h-9" @click="advanceOrder(order.id, 'concluido')" />
                                    <Button icon="pi pi-ellipsis-v" text rounded size="small" class="!w-9 !h-9 !text-surface-400 hover:!text-white hover:!bg-surface-700" @click="toggleMenu($event, order)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <Menu ref="menu" :model="menuItems" :popup="true" class="!bg-surface-900 !border-surface-700" />

        <Dialog v-model:visible="addressDialog.isOpen" modal header="Alterar Endereço" :style="{ width: '90%', maxWidth: '500px' }" 
                :pt="{ root: { class: '!bg-surface-900 !border-surface-700 !rounded-2xl' }, header: { class: '!bg-surface-950 !text-white !border-b !border-surface-800' }, content: { class: '!bg-surface-900 !p-4' } }">
            
            <div v-if="addressDialog.loading" class="flex justify-center p-8">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-primary-500"></i>
            </div>

            <div v-else>
                <div v-if="!addressDialog.showNewForm" class="flex flex-col gap-2">
                    <p class="text-xs font-bold text-surface-500 uppercase mb-2">Endereços Salvos</p>
                    
                    <div v-if="clientAddresses.length === 0" class="text-center py-6 text-surface-500 text-sm border-2 border-dashed border-surface-800 rounded-xl">
                        Nenhum endereço salvo encontrado.
                    </div>

                    <div v-for="(addr, idx) in clientAddresses" :key="idx" 
                         class="p-4 rounded-xl border border-surface-700 bg-surface-950/50 hover:bg-surface-800 cursor-pointer flex items-center gap-3 transition-colors"
                         @click="confirmAddressChange(addr)"
                    >
                        <div class="w-8 h-8 rounded-full bg-surface-800 flex items-center justify-center text-surface-400 shrink-0">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-white truncate">{{ addr.street }}, {{ addr.number }}</div>
                            <div class="text-xs text-surface-400 truncate">{{ addr.district }}</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-surface-600 text-xs"></i>
                    </div>

                    <Button label="Novo Endereço" icon="fa-solid fa-plus" class="mt-4 w-full !bg-surface-800 !border-surface-700 !text-white !h-12 !font-bold !rounded-xl" @click="addressDialog.showNewForm = true" />
                </div>

                <div v-else class="flex flex-col gap-4">
                    <SmartAddressForm v-model="addressDialog.newAddress" />
                    
                    <div class="flex justify-end gap-3 mt-4 border-t border-surface-800 pt-4">
                        <Button label="Voltar" text class="!text-surface-400 hover:!text-white !font-bold" @click="addressDialog.showNewForm = false" />
                        <Button label="Salvar" icon="fa-solid fa-check" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold !rounded-xl" @click="confirmAddressChange(addressDialog.newAddress)" />
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
import { useRouter } from 'vue-router'; 
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import Dialog from 'primevue/dialog';
import SmartAddressForm from '@/components/shared/SmartAddressForm.vue';

const deliveryStore = useDeliveryStore();
const sessionStore = useSessionStore();
const router = useRouter();

// --- STATE RESPONSIVO ---
const activeMobileTab = ref('pending');

const tabs = [
    { id: 'pending', label: 'Pendentes', icon: 'fa-solid fa-bell' },
    { id: 'kitchen', label: 'Cozinha', icon: 'fa-solid fa-fire-burner' },
    { id: 'delivery', label: 'Em Rota', icon: 'fa-solid fa-motorcycle' }
];

const getCount = (tabId) => {
    if (tabId === 'pending') return deliveryStore.pendingOrders.length;
    if (tabId === 'kitchen') return deliveryStore.kitchenOrders.length;
    if (tabId === 'delivery') return deliveryStore.deliveringOrders.length;
    return 0;
};

// ... Resto do código mantido (igual ao anterior) ...
let pollingInterval = null;
const menu = ref(null);
const selectedOrder = ref(null);
const clientAddresses = ref([]);

const addressDialog = reactive({
    isOpen: false,
    loading: false,
    showNewForm: false,
    newAddress: { street: '', number: '', district: '', reference: '' }
});

const menuItems = ref([
    { label: 'Adicionar Itens', icon: 'pi pi-plus', command: () => editOrderInPOS() },
    { label: 'Alterar Endereço', icon: 'pi pi-map-marker', command: () => openAddressModal() },
    { separator: true },
    { label: 'Cancelar Pedido', icon: 'pi pi-times', class: 'text-red-500', command: () => cancelOrder() }
]);

const toggleMenu = (event, order) => {
    selectedOrder.value = order;
    menu.value.toggle(event);
};

const editOrderInPOS = () => {
    if (confirm('Deseja abrir este pedido no Caixa para adicionar itens?')) {
        // Usa o nome do cliente como identificador para o POS
        sessionStore.openSession('counter', selectedOrder.value.client);
    }
};

const cancelOrder = async () => {
    if (!confirm(`Tem certeza que deseja cancelar o pedido #${selectedOrder.value.id}?`)) return;
    await deliveryStore.cancelOrder(selectedOrder.value.id);
};

const openAddressModal = async () => {
    addressDialog.isOpen = true;
    addressDialog.loading = true;
    addressDialog.showNewForm = false;
    clientAddresses.value = [];
    try {
        const resDetail = await api.get(`/order-details/${selectedOrder.value.id}`);
        if(resDetail.data.success) {
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
        addressDialog.showNewForm = true; // Fallback para novo se falhar
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
    if (success) addressDialog.isOpen = false;
};

const advanceOrder = (id, status) => {
    deliveryStore.updateOrderStatus(id, status);
};

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');

onMounted(() => {
    deliveryStore.fetchOrders();
    pollingInterval = setInterval(() => { deliveryStore.fetchOrders(); }, 15000);
});

onUnmounted(() => { if(pollingInterval) clearInterval(pollingInterval); });
</script>

<style scoped>
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
</style>