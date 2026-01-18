<template>
  <div class="p-4 w-full h-full flex flex-col bg-surface-950 overflow-y-auto">
    
    <div class="max-w-7xl w-full mx-auto animate-fade-in">
        
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center">
                <i class="pi pi-truck mr-4 text-primary-500 text-4xl"></i> 
                <div>
                    <h1 class="text-3xl font-bold text-white">Delivery</h1>
                    <p class="text-surface-400 text-sm" v-if="viewMode === 'list'">Gerenciamento de pedidos ativos</p>
                    <p class="text-surface-400 text-sm" v-else>Novo pedido manual</p>
                </div>
            </div>

            <div class="flex gap-3">
                <Button 
                    v-if="viewMode === 'create'" 
                    label="Voltar" 
                    icon="pi pi-arrow-left" 
                    class="!bg-surface-800 !border-surface-700 !text-white" 
                    @click="viewMode = 'list'" 
                />
                
                <Button 
                    v-if="viewMode === 'list'" 
                    label="NOVO PEDIDO" 
                    icon="pi pi-plus" 
                    class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-bold shadow-lg shadow-primary-500/20" 
                    @click="viewMode = 'create'" 
                />
            </div>
        </div>

        <div v-if="viewMode === 'list'" class="animate-fade-in">
            
            <div v-if="orders.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div 
                    v-for="order in orders" 
                    :key="order.id" 
                    class="bg-surface-900 border border-surface-800 rounded-xl p-5 hover:border-primary-500/50 transition-all cursor-pointer group shadow-lg relative overflow-hidden"
                >
                    <div v-if="order.legacy_id" class="absolute top-0 right-0 w-0 h-0 border-t-[30px] border-t-surface-700 border-l-[30px] border-l-transparent"></div>

                    <div class="flex justify-between items-start mb-3">
                        <span class="bg-surface-800 text-surface-300 text-xs font-bold px-2 py-1 rounded">#{{ order.id }}</span>
                        <span class="text-primary-400 text-xs font-bold flex items-center">
                            <i class="pi pi-clock mr-1"></i> {{ order.time_elapsed }}
                        </span>
                    </div>
                    
                    <div class="mb-4">
                        <h3 class="text-white font-bold text-lg truncate">{{ order.client }}</h3>
                        <p class="text-surface-400 text-sm truncate flex items-center mt-1">
                            <i class="pi pi-map-marker mr-1 text-surface-600"></i>
                            {{ order.address || 'Retirada' }}
                        </p>
                    </div>

                    <div class="flex justify-between items-end border-t border-surface-800 pt-3">
                        <div class="flex flex-col">
                            <span class="text-[10px] uppercase text-surface-500 font-bold">Status</span>
                            <span class="text-white font-bold text-sm bg-green-500/10 text-green-400 px-2 py-0.5 rounded inline-block mt-1">
                                {{ order.status }}
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="text-white font-bold text-xl">R$ {{ formatMoney(order.total) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-20 bg-surface-900/50 rounded-2xl border border-dashed border-surface-800 text-center">
                <div class="bg-surface-800 p-6 rounded-full mb-4">
                    <i class="pi pi-inbox text-4xl text-surface-500"></i>
                </div>
                <h3 class="text-xl text-white font-bold mb-2">Sem pedidos ativos</h3>
                <p class="text-surface-400 max-w-xs">Tudo tranquilo por aqui. Os novos pedidos aparecerão nesta tela.</p>
                <Button label="Criar Pedido Manual" text class="mt-4 !text-primary-500" @click="viewMode = 'create'" />
            </div>

        </div>

        <div v-else class="bg-surface-900 border border-surface-800 rounded-2xl shadow-2xl p-8 animate-fade-in-up max-w-4xl mx-auto">
            
            <div class="mb-8">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-primary-500/20 text-primary-500 flex items-center justify-center mr-3 text-sm">1</span>
                    Cliente
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-surface-400">Telefone / Busca</label>
                        <AutoComplete 
                            v-model="phoneSearch" 
                            :suggestions="clientSuggestions" 
                            field="phone"
                            placeholder="Digite telefone ou nome..." 
                            class="w-full"
                            @complete="handleClientSearch"
                            @item-select="onClientSelect"
                            @blur="onPhoneBlur"
                            :pt="{ input: { class: '!bg-surface-950 !border-surface-700 !w-full' }, panel: { class: '!bg-surface-900 !border-surface-700' } }"
                        >
                            <template #option="slotProps">
                                <div class="flex flex-col p-1">
                                    <span class="font-bold text-white">{{ slotProps.option.name }}</span>
                                    <span class="text-xs text-surface-400">{{ slotProps.option.phone }}</span>
                                </div>
                            </template>
                        </AutoComplete>
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-surface-400">Nome Completo</label>
                        <InputText v-model="userStore.onboarding.fullName" class="!bg-surface-950 !border-surface-700" />
                    </div>
                </div>
            </div>

            <div class="h-px bg-surface-800 w-full mb-8"></div>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                    <span class="w-8 h-8 rounded-full bg-primary-500/20 text-primary-500 flex items-center justify-center mr-3 text-sm">2</span>
                    Endereço
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                     <div class="col-span-1 md:col-span-3 flex flex-col gap-2">
                        <label class="text-sm font-bold text-surface-400">Rua</label>
                        <AutoComplete 
                            v-model="store.selectedStreet" 
                            :suggestions="store.streetSuggestions" 
                            optionLabel="name" 
                            placeholder="Buscar rua..."
                            @complete="searchRua" 
                            @item-select="onRuaSelect"
                            class="w-full"
                            :pt="{ input: { class: '!bg-surface-950 !border-surface-700 !w-full' }, panel: { class: '!bg-surface-900 !border-surface-700' } }"
                        >
                             <template #option="slotProps">
                                <div class="flex items-center w-full p-1">
                                    <i v-if="slotProps.option.id === 'new_street'" class="pi pi-plus-circle mr-3 text-primary-500"></i>
                                    <i v-else class="pi pi-map-marker mr-3 text-surface-400"></i>
                                    <div :class="{'text-primary-500 font-bold': slotProps.option.id === 'new_street'}">
                                        {{ slotProps.option.name }}
                                        <span v-if="slotProps.option.msg" class="block text-xs text-surface-400 font-normal mt-1">{{ slotProps.option.msg }}</span>
                                    </div>
                                </div>
                            </template>
                        </AutoComplete>
                     </div>
                     <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-surface-400">Número</label>
                        <InputText v-model="store.streetNumber" @blur="store.calculateFreight" @keydown.enter="store.calculateFreight" class="!bg-surface-950 !border-surface-700" />
                     </div>
                </div>
                
                <div v-if="store.isCalculating" class="mt-4 flex items-center text-primary-400 text-sm animate-pulse">
                    <i class="pi pi-spin pi-spinner mr-2"></i> Calculando...
                </div>

                <div v-else-if="store.calculatedBairro" class="mt-6 bg-primary-500/10 border border-primary-500/20 rounded-xl p-4 flex justify-between items-center animate-fade-in">
                    <div>
                        <div class="text-primary-400 text-xs font-bold uppercase">Entregar em</div>
                        <div class="text-white font-bold text-lg">{{ store.calculatedBairro }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-surface-400 text-xs font-bold uppercase">Taxa</div>
                        <div class="text-white font-bold text-2xl">R$ {{ formatMoney(store.deliveryFee) }}</div>
                    </div>
                </div>
            </div>

            <Button 
                label="INICIAR PEDIDO" 
                icon="pi pi-arrow-right" 
                iconPos="right"
                class="w-full h-14 !bg-primary-500 hover:!bg-primary-400 !border-none !font-bold text-lg !text-surface-900 shadow-lg shadow-primary-500/20"
                :disabled="!store.isValidAddress || !userStore.onboarding.fullName"
                @click="startDeliverySession"
            />
        </div>

    </div>

    <Dialog v-model:visible="store.showCreateStreetModal" modal header="Nova Rua" :style="{ width: '400px' }" :pt="{ root: { class: '!bg-surface-900 !border-surface-800' }, header: { class: '!text-white !bg-surface-900 !border-b !border-surface-800' }, content: { class: '!bg-surface-900 !p-6' } }">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label class="text-surface-300 text-sm font-bold">Nome</label>
                <InputText v-model="store.newStreetName" readonly class="!bg-surface-950 !border-surface-700 !text-white" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-surface-300 text-sm font-bold">Bairro</label>
                <Dropdown v-model="selectedBairroId" :options="store.availableBairros" optionLabel="name" optionValue="id" placeholder="Selecione..." class="!bg-surface-950 !border-surface-700" filter />
            </div>
            <Button label="Salvar Rua" icon="pi pi-check" class="!bg-primary-500 !text-surface-900 !font-bold !border-none mt-2" :loading="store.isCreatingStreet" @click="store.createStreet(selectedBairroId)" />
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useSessionStore } from '@/stores/session-store';
import { useUserStore } from '@/stores/user-store';
import { useRouter } from 'vue-router';
import api from '@/services/api'; 
import AutoComplete from 'primevue/autocomplete';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const store = useDeliveryStore();
const sessionStore = useSessionStore();
const userStore = useUserStore();
const router = useRouter();

// Estados
const viewMode = ref('list');
const orders = ref([]);
const selectedBairroId = ref(null);
const phoneSearch = ref("");
const clientSuggestions = ref([]);
let pollingInterval = null;

const formatMoney = (val) => val ? val.toFixed(2).replace('.', ',') : '0,00';

// --- DASHBOARD: LISTAR PEDIDOS ---
const fetchOrders = async () => {
    try {
        const response = await api.get('/orders');
        if (response.data.success) {
            orders.value = response.data.orders;
        }
    } catch (e) {
        console.error('Erro ao buscar pedidos', e);
    }
};

onMounted(() => {
    fetchOrders();
    // Atualiza a cada 30 segundos (Polling simples)
    pollingInterval = setInterval(fetchOrders, 30000);
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
});

// --- CRIAÇÃO DE PEDIDO ---
const handleClientSearch = async (event) => {
    const query = event.query;
    if (query.length > 2) {
        const results = await store.searchClients(query);
        clientSuggestions.value = results;
    }
};

const onClientSelect = (event) => {
    const client = event.value;
    userStore.onboarding.fullName = client.name;
    userStore.onboarding.whatsapp = client.phone;
    phoneSearch.value = client.phone; 
};

const onPhoneBlur = () => {
    const val = typeof phoneSearch.value === 'object' ? phoneSearch.value.phone : phoneSearch.value;
    userStore.onboarding.whatsapp = val;
};

const searchRua = (event) => store.searchStreets(event.query);

const onRuaSelect = (event) => {
    const selected = event.value;
    if (selected.id === 'new_street') {
        store.selectedStreet = null; 
        store.newStreetName = selected.original_term; 
        store.fetchBairros(); 
        selectedBairroId.value = null;
        store.showCreateStreetModal = true; 
    } else {
        setTimeout(() => {
            const numInput = document.querySelector('input[placeholder="Nº"]'); // Ajustado para não quebrar se mudar o placeholder
            if (!numInput) { 
                // Fallback de foco se o seletor falhar
                const inputs = document.querySelectorAll('.p-inputtext');
                if(inputs[1]) inputs[1].focus();
            }
        }, 100);
    }
};

const startDeliverySession = async () => {
    if (!userStore.onboarding.fullName || !userStore.onboarding.whatsapp) {
        // Validação básica se falta CPF vai pro onboarding
        if (!userStore.onboarding.cpf) {
             router.push('/onboarding');
             return;
        }
    }

    const deliveryData = {
        name: userStore.onboarding.fullName,
        phone: userStore.onboarding.whatsapp,
        cpf: userStore.onboarding.cpf,
        address: {
            street: store.selectedStreet?.name,
            number: store.streetNumber,
            neighborhood: store.calculatedBairro,
            fee: store.deliveryFee
        }
    };

    const success = await sessionStore.openSession('delivery', null, deliveryData);
    if (success) {
        router.push('/tables'); 
    }
};
</script>