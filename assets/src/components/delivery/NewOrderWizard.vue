<template>
  <component 
    :is="componentType"
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    :modal="true"
    :position="isMobile ? 'bottom' : 'center'"
    :dismissableMask="true"
    :closable="false"
    :style="modalStyle"
    class="bg-surface-1 transition-all duration-300"
    :pt="ptOptions"
  >
    <div class="flex flex-col h-full w-full overflow-hidden relative bg-surface-1">
        
        <div class="shrink-0 z-20 relative bg-surface-1 pb-2">
            
            <div v-if="isMobile" class="w-full flex justify-center pt-3 pb-1 cursor-grab active:cursor-grabbing" @click="$emit('update:visible', false)">
                <div class="w-12 h-1.5 bg-surface-3/50 rounded-full"></div>
            </div>

            <div class="flex items-center justify-between px-6 pt-5 pb-2">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-surface-2 flex items-center justify-center text-primary-500 shadow-inner transition-all duration-500">
                        <transition name="scale" mode="out-in">
                            <i v-if="step === 1" class="fa-solid fa-user-check text-xl"></i>
                            <i v-else class="fa-solid fa-map-location-dot text-xl"></i>
                        </transition>
                    </div>
                    
                    <div class="flex flex-col">
                        <h2 class="text-xl font-black text-surface-0 leading-none">Novo Pedido</h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs font-bold text-surface-400">Passo {{ step }} de 2</span>
                            <div class="flex gap-1">
                                <div class="w-1.5 h-1.5 rounded-full transition-colors" :class="step >= 1 ? 'bg-primary-500' : 'bg-surface-3'"></div>
                                <div class="w-1.5 h-1.5 rounded-full transition-colors" :class="step >= 2 ? 'bg-primary-500' : 'bg-surface-3'"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button 
                    v-if="step === 2"
                    class="w-10 h-10 rounded-full flex items-center justify-center transition-all bg-surface-2 text-surface-400 hover:text-surface-0 hover:bg-surface-3"
                    @click="step = 1"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <button 
                    v-else
                    class="w-10 h-10 rounded-full flex items-center justify-center transition-all bg-surface-2 text-surface-400 hover:text-surface-0 hover:bg-surface-3"
                    @click="$emit('update:visible', false)"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-hidden relative bg-surface-1">
            
            <transition name="slide-fade">
                <div v-if="step === 1" class="absolute inset-0 flex flex-col p-6 overflow-y-auto scrollbar-thin">
                    
                    <div class="bg-surface-2 p-5 rounded-[24px] relative border border-transparent shadow-sm mb-6">
                        <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-magnifying-glass text-primary-500"></i> Buscar Cliente
                        </label>
                        
                        <div class="relative w-full group">
                            <AutoComplete 
                                v-model="searchQuery" 
                                :suggestions="clientSuggestions" 
                                @complete="searchClient" 
                                @item-select="onClientSelect" 
                                @keydown.enter.prevent="handleEnterSearch"
                                field="label"
                                placeholder="Nome, CPF ou Telefone..."
                                class="w-full"
                                :pt="{ 
                                    input: { class: '!bg-surface-1 !border-none !text-surface-0 !h-14 !rounded-2xl !w-full !pl-5 font-bold !text-base placeholder:!text-surface-500 focus:!ring-2 focus:!ring-primary-500/50 transition-all' }, 
                                    panel: { class: '!bg-surface-3 !border-none !rounded-2xl !shadow-2xl !mt-2' },
                                    item: { class: '!text-surface-200 hover:!bg-surface-4 !rounded-lg !m-1' }
                                }"
                            >
                                <template #option="slotProps">
                                    <div class="flex flex-col py-1.5 px-1">
                                        <span class="font-bold text-surface-0 text-sm">{{ slotProps.option.name }}</span>
                                        <div class="flex gap-2 text-[10px] text-surface-400 font-medium">
                                            <span v-if="slotProps.option.cpf"><i class="fa-regular fa-id-card mr-1"></i>{{ formatCPF(slotProps.option.cpf) }}</span>
                                            <span v-if="slotProps.option.phone"><i class="fa-brands fa-whatsapp mr-1"></i>{{ formatPhone(slotProps.option.phone) }}</span>
                                        </div>
                                    </div>
                                </template>
                            </AutoComplete>
                        </div>
                        
                        <div v-if="foundType === 'internal'" class="mt-3 flex items-center gap-2 bg-green-500/10 text-green-400 p-3 rounded-xl border border-green-500/20 animate-fade-in">
                            <i class="fa-solid fa-circle-check text-lg"></i>
                            <div class="flex flex-col">
                                <span class="text-xs font-black uppercase">Cliente Encontrado</span>
                                <span class="text-[10px] font-medium opacity-80">Dados carregados do sistema.</span>
                            </div>
                        </div>
                        
                        <div v-if="showGovConfirm" class="mt-3 bg-surface-3 p-4 rounded-xl border border-surface-4/30 animate-fade-in">
                            <p class="text-xs text-surface-200 font-bold mb-3 flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-yellow-500"></i> Não encontrado. Consultar Receita?
                            </p>
                            <Button 
                                label="Consultar CPF na Receita" 
                                icon="fa-solid fa-globe" 
                                class="!w-full !bg-surface-1 hover:!bg-primary-500 hover:!text-surface-950 !text-primary-400 !border-primary-500/30 !border !font-bold"
                                :loading="isFetchingGov"
                                @click="fetchGovData"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest pl-1">Nome do Cliente</label>
                            <InputText 
                                v-model="form.name" 
                                class="!bg-surface-2 !border-none !text-surface-0 !h-14 !rounded-2xl !pl-5 !font-bold focus:!ring-2 focus:!ring-primary-500/50" 
                                :disabled="foundType === 'internal' || foundType === 'gov'" 
                                placeholder="Digite o nome..."
                            />
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest pl-1">Telefone / WhatsApp</label>
                            <InputMask 
                                v-model="form.phone" 
                                mask="(99) 99999-9999" 
                                class="!bg-surface-2 !border-none !text-surface-0 !h-14 !rounded-2xl !pl-5 !font-bold focus:!ring-2 focus:!ring-primary-500/50" 
                                placeholder="(00) 00000-0000"
                                ref="phoneInput" 
                            />
                        </div>
                    </div>

                    <div class="mt-auto">
                        <label class="text-[10px] uppercase font-black text-surface-500 tracking-widest pl-1 mb-3 block">Tipo de Pedido</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button 
                                class="group relative h-20 bg-surface-2 hover:bg-surface-3 text-surface-400 hover:text-surface-0 rounded-[20px] font-black uppercase text-xs transition-all flex flex-col items-center justify-center gap-1 border border-transparent hover:border-surface-400 active:scale-95"
                                @click="selectType('pickup')"
                            >
                                <i class="fa-solid fa-bag-shopping text-2xl mb-1 group-hover:scale-110 transition-transform"></i>
                                Retirada
                            </button>
                            
                            <button 
                                class="group relative h-20 bg-primary-500/10 hover:bg-primary-500 text-primary-400 hover:text-surface-950 rounded-[20px] font-black uppercase text-xs transition-all flex flex-col items-center justify-center gap-1 border border-primary-500/30 hover:border-transparent active:scale-95"
                                @click="selectType('delivery')"
                            >
                                <i class="fa-solid fa-motorcycle text-2xl mb-1 group-hover:scale-110 transition-transform"></i>
                                Delivery
                            </button>
                        </div>
                    </div>
                </div>
            </transition>

            <transition name="slide-left">
                <div v-if="step === 2" class="absolute inset-0 flex flex-col h-full">
                    <div class="flex-1 p-6 overflow-y-auto scrollbar-thin">
                        <div class="bg-surface-2/50 border border-surface-3/10 rounded-[24px] p-1 h-full">
                            <SmartAddressWidget 
                                v-model="form.address" 
                                :client-id="form.client_id"
                                :preloaded-addresses="foundType === 'internal' ? null : []" 
                                mode="selection"
                                class="h-full"
                            />
                        </div>
                    </div>
                    
                    <div class="p-6 pt-4 bg-surface-1 border-t border-surface-3/10 shrink-0 z-20">
                        <Button 
                            label="INICIAR PEDIDO" 
                            icon="fa-solid fa-check" 
                            class="!w-full !h-14 !bg-primary-600 hover:!bg-primary-500 !border-none !text-surface-950 !font-black !rounded-full !text-lg shadow-xl"
                            :disabled="!form.address || !form.address.street"
                            :loading="isCreating"
                            @click="handleCreateOrder"
                        />
                    </div>
                </div>
            </transition>

        </div>
    </div>
  </component>
</template>

<script setup>
import { ref, reactive, watch, nextTick, computed } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useSessionStore } from '@/stores/session-store';
import { useFormat } from '@/composables/useFormat';
import { useMobile } from '@/composables/useMobile'; // <--- Importante
import api from '@/services/api';
import { notify } from '@/services/notify';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer'; // <--- Importante
import AutoComplete from 'primevue/autocomplete';
import InputText from 'primevue/inputtext';
import InputMask from 'primevue/inputmask';
import Button from 'primevue/button';
import SmartAddressWidget from '@/components/shared/SmartAddressWidget.vue';

const props = defineProps(['visible']);
const emit = defineEmits(['update:visible', 'success']);

const { isMobile } = useMobile();
const deliveryStore = useDeliveryStore();
const sessionStore = useSessionStore();
const { cleanDigits, formatCPF, formatPhone } = useFormat();

const step = ref(1);
const isCreating = ref(false);
const isCheckingCpf = ref(false);
const isFetchingGov = ref(false);
const showGovConfirm = ref(false);
const searchQuery = ref('');
const foundType = ref(null);
const clientSuggestions = ref([]);
const phoneInput = ref(null);

const form = reactive({
    cpf: '',
    name: '',
    phone: '',
    dob: '',
    client_id: null,
    type: 'delivery',
    address: null
});

// --- Lógica Híbrida (Drawer vs Dialog) ---
const componentType = computed(() => isMobile.value ? Drawer : Dialog);

// 1. ALTERAÇÃO NO MODAL STYLE (Adicione height no else)
const modalStyle = computed(() => isMobile.value 
    ? { width: '100%', height: '90vh', maxHeight: '95vh' } 
    : { width: '600px', height: '650px', maxHeight: '90vh' } // <--- Altura Fixa adicionada
);

const ptOptions = computed(() => {
    const common = {
        header: { class: '!hidden' }, 
        // 2. ALTERAÇÃO NO CONTENT (Adicione !h-full no final)
        content: { class: '!bg-surface-1 !p-0 !overflow-hidden !flex !flex-col !rounded-t-[32px] md:!rounded-[28px] !h-full' }
    };

    if (isMobile.value) {
        return {
            ...common,
            root: { class: '!bg-surface-1 !border-none !rounded-t-[32px] !shadow-2xl !overflow-hidden' },
            mask: { class: '!bg-surface-base/80 backdrop-blur-md' }
        };
    } else {
        return {
            ...common,
            root: { class: '!bg-surface-1 !border-none !rounded-[28px] !overflow-hidden !shadow-2xl' },
            mask: { class: '!bg-surface-base/60 backdrop-blur-sm' }
        };
    }
});

// Reset ao abrir
watch(() => props.visible, (val) => {
    if(val) {
        step.value = 1;
        foundType.value = null;
        showGovConfirm.value = false;
        searchQuery.value = '';
        Object.assign(form, { cpf: '', name: '', phone: '', dob: '', client_id: null, type: 'delivery', address: null });
    }
});

const searchClient = async (event) => {
    try {
        const { data } = await api.get(`/search-clients?q=${event.query}`);
        if(data.success) {
            clientSuggestions.value = data.clients.map(c => ({
                ...c,
                label: c.name
            }));
        }
    } catch(e) { console.error(e); }
};

const onClientSelect = (event) => {
    const client = event.value;
    form.name = client.name;
    form.phone = client.phone || ''; 
    form.cpf = client.cpf || '';
    form.client_id = client.id;
    foundType.value = 'internal';
    showGovConfirm.value = false;
};

const handleEnterSearch = async () => {
    const term = typeof searchQuery.value === 'string' ? searchQuery.value : searchQuery.value?.label;
    if (!term) return;
    const digits = cleanDigits(term);
    if (digits.length === 11) {
        form.cpf = digits;
        await checkCpf(digits);
    }
};

const checkCpf = async (cpfDigits) => {
    isCheckingCpf.value = true;
    showGovConfirm.value = false;
    foundType.value = null;
    try {
        const { data } = await api.get(`/search-clients?q=${cpfDigits}`);
        if (data.success && data.clients.length > 0) {
            const client = data.clients[0];
            searchQuery.value = client; 
            onClientSelect({ value: client });
            notify('success', 'Cliente Encontrado', 'Dados carregados.');
        } else {
            showGovConfirm.value = true;
        }
    } catch (e) {
        showGovConfirm.value = true; 
    } finally {
        isCheckingCpf.value = false;
    }
};

const fetchGovData = async () => {
    const cpf = cleanDigits(form.cpf);
    if (!cpf) return;
    isFetchingGov.value = true;
    try {
        const { data } = await api.post('/onboarding/validate-cpf', { cpf });
        if (data.success) {
            form.name = data.data.name;
            foundType.value = 'gov';
            form.client_id = null;
            showGovConfirm.value = false;
            notify('success', 'Receita Federal', 'Dados encontrados. Informe o telefone.');
            nextTick(() => { if (phoneInput.value?.$el) phoneInput.value.$el.focus(); });
        }
    } catch (e) {
        notify('error', 'Erro', e.response?.data?.message || 'Falha na consulta.');
    } finally {
        isFetchingGov.value = false;
    }
};

const isStep1Valid = computed(() => form.name && form.phone);

const selectType = (type) => {
    if (!isStep1Valid.value) {
        notify('warn', 'Atenção', 'Preencha os dados do cliente primeiro.');
        return;
    }
    form.type = type;
    if (type === 'pickup') handleCreateOrder();
    else step.value = 2;
};

const handleCreateOrder = async () => {
    isCreating.value = true;
    const payload = {
        type: form.type,
        client_name: form.name,
        client_phone: cleanDigits(form.phone),
        client_id: form.client_id,
        client_cpf: cleanDigits(form.cpf),
        client_dob: null,
        address: form.type === 'delivery' ? form.address : null
    };

    const sessionId = await deliveryStore.createOrder(payload);
    isCreating.value = false;
    
    if (sessionId) {
        await sessionStore.resumeSession(sessionId, null, true);
        emit('update:visible', false);
        emit('success', sessionId);
    }
};
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
.slide-fade-enter-active, .slide-fade-leave-active { transition: all 0.3s ease; }
.slide-fade-enter-from { opacity: 0; transform: translateX(-20px); }
.slide-fade-leave-to { opacity: 0; transform: translateX(20px); }

.slide-left-enter-active { transition: all 0.3s ease-out; }
.slide-left-enter-from { opacity: 0; transform: translateX(30px); }

.scale-enter-active { transition: all 0.2s ease; }
.scale-enter-from { transform: scale(0); opacity: 0; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>