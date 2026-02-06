<template>
  <div class="flex flex-col h-full bg-surface-1 relative overflow-hidden">
    
    <div v-if="loading" class="absolute inset-0 bg-surface-1/80 backdrop-blur-sm z-50 flex items-center justify-center rounded-xl">
        <i class="fa-solid fa-circle-notch fa-spin text-2xl text-primary-500"></i>
    </div>

    <transition name="fade-slide" mode="out-in">
        <div v-if="viewMode === 'list'" key="list" class="flex flex-col h-full overflow-hidden">
            
            <div v-if="formattedAddresses.length === 0" class="flex-1 flex flex-col items-center justify-center p-8 text-center m-4 animate-fade-in">
                <div class="w-20 h-20 bg-surface-2 rounded-full flex items-center justify-center mb-6 shadow-[0_8px_30px_rgba(0,0,0,0.12)]">
                    <i class="fa-solid fa-map-location-dot text-3xl text-surface-400"></i>
                </div>
                <h3 class="text-lg font-black text-surface-0 mb-2">Onde vamos entregar?</h3>
                <p class="text-sm font-medium text-surface-400 mb-8 max-w-[240px] leading-relaxed">
                    Cadastre seus endereços para agilizar seus pedidos futuros.
                </p>
                <button 
                    class="w-full h-14 rounded-full bg-primary-500 text-surface-950 font-black text-sm uppercase tracking-widest hover:bg-primary-400 transition-all shadow-lg active:scale-95 flex items-center justify-center gap-3" 
                    @click="addNew"
                >
                    <i class="fa-solid fa-plus"></i> Adicionar Endereço
                </button>
            </div>

            <div v-else class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin">
                <div v-for="(addr, idx) in formattedAddresses" :key="addr.id || idx" 
                     class="relative p-5 rounded-[24px] transition-all group cursor-pointer border border-transparent"
                     :class="[
                        isSelected(addr) 
                            ? 'bg-surface-2 ring-2 ring-primary-500 shadow-xl shadow-primary-900/10' 
                            : 'bg-surface-2 hover:bg-surface-3 active:scale-[0.98]'
                     ]"
                     @click="handleSelect(addr)"
                >
                    <div class="absolute top-5 right-5 flex gap-2">
                        <span v-if="addr.is_primary" class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 text-[9px] font-black uppercase px-2 py-1 rounded-full flex items-center gap-1">
                            <i class="fa-solid fa-star text-[8px]"></i> Principal
                        </span>
                        
                        <div v-if="mode === 'selection' && isSelected(addr)" class="w-6 h-6 rounded-full bg-primary-500 flex items-center justify-center shadow-lg animate-bounce-in">
                            <i class="fa-solid fa-check text-surface-950 text-xs"></i>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 pr-2">
                        <div class="mt-0.5 w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition-colors"
                             :class="isSelected(addr) ? 'bg-primary-500 text-surface-950' : 'bg-surface-1 text-surface-400'">
                            <i :class="getIconForLabel(addr.displayLabel)" class="text-sm"></i>
                        </div>

                        <div class="min-w-0 pr-8">
                            <div class="font-black text-surface-0 text-base leading-tight mb-1">
                                {{ addr.displayLabel }}
                            </div>
                            
                            <div class="text-xs font-bold text-surface-400 mb-2.5 leading-snug">
                                {{ cleanText(addr.street) }}, {{ addr.number }}
                                <span v-if="addr.district" class="block text-[10px] font-medium opacity-60 mt-0.5 uppercase tracking-wide">{{ cleanText(addr.district) }}</span>
                            </div>
                            
                            <div class="flex flex-wrap gap-2">
                                <div v-if="addr.reference" class="text-[9px] text-surface-400 font-bold uppercase tracking-wider bg-surface-1 px-2 py-1 rounded-lg flex items-center gap-1.5 border border-surface-3/10">
                                    <i class="fa-solid fa-info-circle text-surface-500"></i> {{ cleanText(addr.reference) }}
                                </div>
                                <div v-if="addr.photo_url" class="text-[9px] text-blue-400 font-bold uppercase bg-blue-500/10 px-2 py-1 rounded-lg flex items-center gap-1.5 border border-blue-500/20">
                                    <i class="fa-solid fa-image"></i> Foto da Fachada
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-surface-1/50 flex justify-end gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
                        <button class="h-8 px-3 rounded-full text-[10px] uppercase tracking-wider text-surface-400 hover:text-surface-0 hover:bg-surface-1 font-black flex items-center gap-1.5 transition-all" @click.stop="editAddress(addr)">
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                        <button class="h-8 px-3 rounded-full text-[10px] uppercase tracking-wider text-red-400 hover:text-red-300 hover:bg-red-500/10 font-black flex items-center gap-1.5 transition-all" @click.stop="deleteAddress(addr)">
                            <i class="fa-solid fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>

                <div class="pt-2 sticky bottom-0 bg-gradient-to-t from-surface-1 via-surface-1 to-transparent pb-1">
                    <button 
                        class="w-full h-14 rounded-full border border-surface-3/50 bg-surface-2 text-surface-400 font-black text-xs uppercase hover:bg-surface-3 hover:text-surface-0 transition-all flex items-center justify-center gap-2 shadow-sm active:scale-95 group"
                        @click="addNew"
                    >
                        <div class="w-6 h-6 rounded-full bg-surface-3 group-hover:bg-surface-4 flex items-center justify-center transition-colors">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                        </div>
                        Novo Endereço
                    </button>
                </div>
            </div>
        </div>

        <div v-else key="form" class="flex flex-col h-full animate-slide-left bg-surface-1">
            <div class="flex items-center justify-between px-6 pt-6 pb-4 shrink-0">
                <button class="w-10 h-10 rounded-full bg-surface-2 text-surface-400 hover:text-surface-0 flex items-center justify-center transition-colors active:scale-90" @click="viewMode = 'list'">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <span class="text-sm font-black text-surface-0 uppercase tracking-widest">{{ isEditing ? 'Editar' : 'Novo' }}</span>
                <div class="w-10"></div> </div>

            <div class="flex-1 overflow-y-auto px-6 pb-6 scrollbar-hide">
                <SmartAddressForm v-model="form" />
            </div>
            
            <div class="p-6 pt-4 border-t border-surface-3/10 shrink-0 bg-surface-1">
                <button 
                    class="w-full h-14 rounded-[20px] bg-primary-500 hover:bg-primary-400 text-surface-950 font-black text-sm uppercase tracking-widest shadow-xl shadow-primary-900/20 transition-all flex items-center justify-center gap-3 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="isSaving"
                    @click="save"
                >
                    <i v-if="isSaving" class="fa-solid fa-circle-notch fa-spin"></i>
                    <span v-else>Salvar Endereço</span>
                    <i v-if="!isSaving" class="fa-solid fa-check"></i>
                </button>
            </div>
        </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, computed } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import SmartAddressForm from '@/components/shared/SmartAddressForm.vue';

const props = defineProps({
    clientId: { type: [Number, String], default: null }, 
    modelValue: { type: Object, default: null }, 
    mode: { type: String, default: 'selection' }, 
    preloadedAddresses: { type: Array, default: () => [] } 
});

const emit = defineEmits(['update:modelValue', 'address-saved', 'address-deleted']);

const viewMode = ref('list'); 
const loading = ref(false);
const isSaving = ref(false);
const addresses = ref([]);
const isEditing = ref(false);

const form = reactive({ id: null, street: '', number: '', district: '', reference: '', nickname: '', is_primary: false, facade_photo: null });

const endpoints = computed(() => {
    if (props.clientId) {
        return { get: `/clients/${props.clientId}`, save: '/clients/address', delete: '/clients/address' };
    }
    return { get: '/my-profile', save: '/clients/address', delete: '/clients/address' }; 
});

// Lógica de Apelidos "Casa 1, Casa 2"
const formattedAddresses = computed(() => {
    if (!addresses.value || addresses.value.length === 0) return [];
    
    const noNickList = addresses.value.filter(a => !a.nickname);
    let namelessCounter = 1;

    return addresses.value.map(addr => {
        const copy = { ...addr };
        
        if (copy.nickname) {
            copy.displayLabel = copy.nickname;
        } else {
            if (noNickList.length > 1) {
                copy.displayLabel = `Casa ${namelessCounter}`;
                if (!addr.nickname) namelessCounter++;
            } else {
                copy.displayLabel = 'Casa';
            }
        }
        return copy;
    });
});

const getIconForLabel = (label) => {
    const l = label ? label.toLowerCase() : '';
    if (l.includes('trabalho') || l.includes('escritório')) return 'fa-solid fa-briefcase';
    if (l.includes('amor') || l.includes('namorada')) return 'fa-solid fa-heart';
    if (l.includes('praia')) return 'fa-solid fa-umbrella-beach';
    return 'fa-solid fa-house'; 
};

watch(() => props.clientId, async (newId) => {
    if (newId) await fetchAddresses();
    else addresses.value = [];
});

const cleanText = (str) => {
    if (!str) return '';
    try { return str.replace(/u([0-9a-fA-F]{4})/g, (_, hex) => String.fromCharCode(parseInt(hex, 16))); } catch (e) { return str; }
};

const fetchAddresses = async () => {
    if (props.preloadedAddresses.length > 0 && !props.clientId) {
        addresses.value = props.preloadedAddresses;
        return;
    }
    loading.value = true;
    try {
        const { data } = await api.get(endpoints.value.get);
        if (data.success) {
            const entity = data.client || data.user;
            addresses.value = entity?.addresses || [];
            
            if (props.mode === 'selection' && addresses.value.length > 0 && !props.modelValue) {
                const primary = addresses.value.find(a => a.is_primary) || addresses.value[0];
                handleSelect(primary);
            }
        }
    } catch (e) { console.error(e); } finally { loading.value = false; }
};

const isSelected = (addr) => {
    if (!props.modelValue) return false;
    if (props.modelValue.id && addr.id) return String(props.modelValue.id) === String(addr.id);
    return false;
};

const handleSelect = (addr) => {
    if (props.mode === 'selection') {
        const cleanAddr = { 
            ...addr, 
            street: cleanText(addr.street),
            displayLabel: addr.displayLabel 
        };
        emit('update:modelValue', cleanAddr);
    }
};

const addNew = () => { resetForm(); isEditing.value = false; viewMode.value = 'form'; };
const editAddress = (addr) => { 
    Object.assign(form, { ...addr, street: cleanText(addr.street) }); 
    isEditing.value = true; 
    viewMode.value = 'form'; 
};
const resetForm = () => { 
    Object.assign(form, { id: null, street: '', number: '', district: '', reference: '', nickname: '', is_primary: false, facade_photo: null }); 
};

const save = async () => {
    if (!form.street || !form.number) { notify('warn', 'Atenção', 'Rua e Número são obrigatórios.'); return; }
    isSaving.value = true;
    try {
        let payload;
        let headers = {};

        if (form.facade_photo instanceof File) {
            const formData = new FormData();
            formData.append('client_id', props.clientId);
            formData.append('facade_photo', form.facade_photo);
            
            const addressData = { ...form };
            delete addressData.facade_photo; 
            
            formData.append('address', JSON.stringify(addressData));
            payload = formData;
            headers = { 'Content-Type': 'multipart/form-data' };
        } else {
            payload = { address: form, client_id: props.clientId };
        }

        const { data } = await api.post(endpoints.value.save, payload, { headers });
        
        if (data.success) {
            notify('success', 'Sucesso', 'Endereço salvo!');
            addresses.value = data.addresses; 
            viewMode.value = 'list';
            
            if (props.mode === 'selection') {
                const saved = addresses.value.find(a => a.id === form.id || (a.street === form.street && a.number === form.number)); 
                if (saved) handleSelect(saved);
            }
            emit('address-saved', data.addresses);
        }
    } catch (e) {
        notify('error', 'Erro', 'Falha ao salvar endereço.');
    } finally {
        isSaving.value = false;
    }
};

const deleteAddress = async (addr) => {
    if (!confirm('Excluir este endereço?')) return;

    // --- SONDA 1: Preparação ---
    console.group('🔥 DEBUG DELETE ADDRESS');
    const endpointURL = endpoints.value.delete;
    const payload = { 
        address_id: addr.id, 
        client_id: props.clientId, // Se for null, backend assume current_user
        street: addr.street,       // Fallback para legados
        number: addr.number        // Fallback para legados
    };
    
    console.log('1. URL Alvo:', endpointURL);
    console.log('2. Payload:', payload);

    // Otimismo: Remove da lista visualmente antes
    const backup = [...addresses.value];
    addresses.value = addresses.value.filter(a => a !== addr);

    try {
        // TENTATIVA 1: Método DELETE Padrão
        console.log('3. Tentando DELETE nativo...');
        const { data } = await api.delete(endpointURL, { data: payload });
        handleSuccess(data, addr);

    } catch (e) {
        console.warn('⚠️ Falha no DELETE nativo:', e.message);
        
        // Se for 404 ou 405, pode ser bloqueio de servidor. Tenta Override via POST.
        if (e.response && (e.response.status === 404 || e.response.status === 405 || e.response.status === 501)) {
            console.log('4. Tentando Fallback (Method Override)...');
            try {
                // TENTATIVA 2: POST masquerading as DELETE
                const { data } = await api.post(endpointURL, payload, {
                    headers: { 'X-HTTP-Method-Override': 'DELETE' }
                });
                handleSuccess(data, addr);
                console.groupEnd();
                return; // Sucesso no fallback
            } catch (e2) {
                console.error('❌ Falha também no Fallback:', e2);
            }
        } else {
            console.error('❌ Erro Real do Backend:', e.response?.data || e);
        }

        // Se chegou aqui, falhou tudo. Reverte.
        addresses.value = backup;
        notify('error', 'Erro', 'Não foi possível excluir o endereço.');
        console.groupEnd();
    }
};

// Helper para evitar duplicar código de sucesso
const handleSuccess = (data, addr) => {
    if (data.success) {
        console.log('✅ SUCESSO! Resposta:', data);
        if (isSelected(addr)) emit('update:modelValue', null);
        
        // Atualiza a lista com a resposta oficial do servidor (importante para reordenar principais)
        if (data.addresses) {
            addresses.value = data.addresses; 
        }
        
        notify('success', 'Removido', 'Endereço excluído.');
        emit('address-deleted');
    }
    console.groupEnd();
};

onMounted(() => { 
    if (props.clientId || !props.clientId) fetchAddresses(); 
});
</script>

<style scoped>
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-slide-enter-from { opacity: 0; transform: translateX(20px); }
.fade-slide-leave-to { opacity: 0; transform: translateX(-20px); }

.animate-slide-left { animation: slideLeft 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.animate-bounce-in { animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.animate-fade-in { animation: fadeIn 0.5s ease-out; }

@keyframes slideLeft { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
@keyframes bounceIn { 0% { transform: scale(0); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>