<template>
  <div class="grid grid-cols-12 gap-4 animate-fade-in relative">
    
    <div v-if="loadingGeo" class="absolute inset-0 z-50 bg-surface-1/60 backdrop-blur-[2px] flex flex-col items-center justify-center rounded-2xl">
        </div>

    <div class="col-span-12 flex flex-col gap-3">
        <div class="flex justify-between items-center px-1">
            <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest">Salvar como</label>
            
            <div v-if="!isFirstAddress" class="flex items-center gap-2 bg-surface-2/50 pl-3 pr-1 py-1 rounded-full border border-surface-3/20">
                <span class="text-[9px] font-bold text-surface-400 uppercase tracking-wide">Principal</span>
                <ToggleSwitch v-model="localData.is_primary" :pt="{ root: { class: '!w-8 !h-4' }, slider: { class: '!bg-surface-400' } }" />
            </div>
            <div v-else class="text-[9px] font-bold text-primary-400 uppercase bg-primary-500/10 border border-primary-500/20 px-2 py-1 rounded-full shadow-sm">
                <i class="fa-solid fa-star mr-1 text-[8px]"></i> Principal
            </div>
        </div>
        
        <div class="flex gap-2 overflow-x-auto scrollbar-hide pb-1">
            <button v-for="nick in nicknames" :key="nick" 
                class="px-4 py-2 rounded-[14px] text-[10px] font-black border transition-all active:scale-95 whitespace-nowrap"
                :class="localData.nickname === nick 
                    ? 'bg-primary-500 text-surface-950 border-primary-500 shadow-md shadow-primary-500/20' 
                    : 'bg-surface-2 text-surface-400 border-surface-3/50 hover:bg-surface-3 hover:text-surface-200'"
                @click="localData.nickname = nick"
            >
                {{ nick }}
            </button>
        </div>
        
        <InputText 
            v-model="localData.nickname" 
            placeholder="Ou digite um nome (Ex: Casa da Praia)" 
            class="!bg-surface-2 !border-none !text-surface-0 !h-12 !rounded-[18px] !pl-5 font-bold text-sm focus:!ring-2 focus:!ring-primary-500/50 placeholder:text-surface-500/70 shadow-sm" 
        />
    </div>

    <div class="col-span-12">
        <button 
            class="w-full h-12 rounded-[18px] font-black text-xs uppercase tracking-wide flex items-center justify-center gap-2 transition-all duration-300 border"
            :class="geoBtnClass"
            :disabled="loadingGeo || geoSuccess"
            @click="getLocation"
        >
            <i v-if="loadingGeo" class="fa-solid fa-circle-notch fa-spin text-lg"></i>
            <i v-else-if="geoSuccess" class="fa-solid fa-check-circle text-lg"></i>
            <i v-else class="fa-solid fa-location-crosshairs text-lg"></i>
            
            <span>{{ geoBtnLabel }}</span>
        </button>
    </div>

    <div class="col-span-12 md:col-span-8 flex flex-col gap-2">
        <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest ml-1">Endereço</label>
        <AutoComplete 
            v-model="selectedStreetObj" 
            :suggestions="deliveryStore.streetSuggestions" 
            optionLabel="name" 
            placeholder="Buscar rua..." 
            class="w-full"
            @complete="searchStreet" 
            @item-select="onStreetSelect"
            @change="onStreetChange"
            :pt="{ 
                root: { class: 'w-full' }, 
                input: { class: '!bg-surface-2 !border-none !w-full !text-surface-0 !h-14 !rounded-[18px] !pl-5 focus:!ring-2 focus:!ring-primary-500 font-bold shadow-sm placeholder:text-surface-500/70' }, 
                panel: { class: '!bg-surface-2 !border border-surface-3/50 !rounded-[20px] !shadow-2xl !mt-2' } 
            }"
        >
            <template #option="slotProps">
                <div class="flex items-center w-full p-3 hover:bg-surface-3 transition-colors rounded-[12px] cursor-pointer gap-3">
                    <div class="w-8 h-8 rounded-full bg-surface-1 flex items-center justify-center text-primary-500 shrink-0"><i class="fa-solid fa-location-dot text-xs"></i></div>
                    <div class="flex flex-col min-w-0">
                        <span class="font-bold text-surface-0 text-sm truncate">{{ slotProps.option.name }}</span>
                        <span v-if="slotProps.option.district" class="text-[10px] text-surface-400 font-bold uppercase tracking-wider">
                            {{ slotProps.option.district }}
                        </span>
                    </div>
                </div>
            </template>
        </AutoComplete>
    </div>

    <div class="col-span-12 md:col-span-4 flex flex-col gap-2">
        <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest ml-1 flex justify-between">
            <span>Número</span>
            <span class="text-surface-500 cursor-pointer hover:text-surface-300 transition-colors" @click="toggleNoNumberState">
                {{ isNoNumber ? 'Sem Número' : 'Informar S/N' }}
            </span>
        </label>
        
        <div class="relative h-14">
            <InputText 
                v-model="localData.number" 
                placeholder="123"
                class="!bg-surface-2 !border-none !text-surface-0 w-full !h-full text-center font-black !rounded-[18px] text-lg focus:!ring-2 focus:!ring-primary-500 shadow-sm transition-all"
                :class="{'!bg-surface-1 !text-surface-500 !border border-surface-3/30 opacity-60 cursor-not-allowed': isNoNumber}"
                :disabled="isNoNumber"
                @update:modelValue="onNumberChange"
                ref="numberInput"
            />
            
            <div class="absolute right-2 top-1/2 -translate-y-1/2">
                <ToggleSwitch 
                    v-model="isNoNumber" 
                    @change="toggleNoNumber" 
                    :pt="{ 
                        root: { class: '!w-10 !h-6' }, 
                        slider: { class: isNoNumber ? '!bg-primary-500' : '!bg-surface-3' } 
                    }" 
                />
            </div>
        </div>
    </div>

    <div class="col-span-12 flex flex-col gap-2">
        <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest ml-1">Bairro</label>
        <div class="relative">
            <InputText 
                v-model="localData.district" 
                class="!bg-surface-1 !border !border-surface-3/30 !text-surface-400 w-full !h-12 !rounded-[18px] !pl-10 font-bold text-sm focus:!ring-0 cursor-default"
                readonly
                tabindex="-1"
                placeholder="Preenchimento automático..."
            />
            <i class="fa-solid fa-map absolute left-4 top-1/2 -translate-y-1/2 text-surface-500"></i>
        </div>
    </div>

    <div class="col-span-12 flex flex-col gap-2 transition-all duration-300">
        <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest ml-1 flex items-center gap-2">
            Complemento / Referência
            <span v-if="isNoNumber" class="text-[9px] bg-red-500/10 text-red-400 px-1.5 py-0.5 rounded border border-red-500/20 font-bold animate-pulse">
                Obrigatório
            </span>
        </label>
        <InputText 
            v-model="localData.reference" 
            :placeholder="isNoNumber ? 'Descreva a fachada, cor do portão...' : 'Apto 101, Fundos, etc'"
            class="!bg-surface-2 !border-none !text-surface-0 !h-12 !rounded-[18px] !pl-5 font-medium shadow-sm transition-all"
            :class="{'ring-2 ring-red-500/40 bg-red-500/5': showReferenceError}"
            @update:modelValue="emitUpdate"
        />
        <small v-if="showReferenceError" class="text-red-400 text-[10px] font-bold px-2">
            Por favor, dê uma referência para ajudar o entregador.
        </small>
    </div>

    <div v-if="isNoNumber" class="col-span-12 animate-slide-down mt-2">
        <label class="flex items-center gap-2 text-[10px] font-black text-surface-400 uppercase tracking-widest ml-1 mb-2">
            <i class="fa-solid fa-camera text-primary-500"></i> Foto da Fachada
            <span class="text-[9px] bg-red-500 text-white px-2 py-0.5 rounded-full font-bold shadow-sm">Obrigatório</span>
        </label>
        
        <div 
            class="bg-surface-2/50 border-2 border-dashed border-surface-3 hover:border-primary-500/50 rounded-3xl p-1 relative transition-all group overflow-hidden"
            :class="{'border-red-500/40 bg-red-500/5': !localData.facade_photo && !localData.photo_url}"
            @click="$refs.fileInput.click()"
        >
            <input type="file" ref="fileInput" class="hidden" accept="image/*" @change="handleFileUpload">
            
            <div class="h-32 w-full rounded-[20px] bg-surface-1 flex flex-col items-center justify-center relative overflow-hidden group-hover:bg-surface-2 transition-colors cursor-pointer">
                
                <img v-if="previewImage || localData.photo_url" 
                     :src="previewImage || localData.photo_url" 
                     class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" 
                />
                
                <div class="absolute inset-0 flex flex-col items-center justify-center transition-all duration-300"
                     :class="(previewImage || localData.photo_url) ? 'bg-black/50 opacity-0 group-hover:opacity-100' : 'opacity-100'">
                    
                    <div class="w-10 h-10 rounded-full bg-surface-0/10 backdrop-blur-md flex items-center justify-center mb-2 border border-white/20">
                        <i class="fa-solid fa-cloud-arrow-up text-white"></i>
                    </div>
                    <span class="text-xs font-bold text-white uppercase tracking-wide">
                        {{ (previewImage || localData.photo_url) ? 'Alterar Foto' : 'Enviar Foto' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import { useUserStore } from '@/stores/user-store';
import { notify } from '@/services/notify';
import AutoComplete from 'primevue/autocomplete';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ street: '', number: '', district: '', reference: '', nickname: '', is_primary: false, facade_photo: null }) }
});
const emit = defineEmits(['update:modelValue']);
const deliveryStore = useDeliveryStore();
const userStore = useUserStore();

const localData = ref({ ...props.modelValue });
const selectedStreetObj = ref(null);
const currentSegments = ref([]); 
const numberInput = ref(null);
const fileInput = ref(null);
const isNoNumber = ref(false);
const loadingGeo = ref(false);
const geoSuccess = ref(false);
const previewImage = ref(null);

const nicknames = ['Casa', 'Trabalho', 'Namorada(o)', 'Família'];

const isFirstAddress = computed(() => !userStore.user?.addresses || userStore.user.addresses.length === 0);

// Validação visual da referência
const showReferenceError = computed(() => {
    // Só mostra erro se for S/N E o campo for curto demais
    return isNoNumber.value && (!localData.value.reference || localData.value.reference.length < 3);
});

// Estilos dinâmicos do botão GPS
const geoBtnClass = computed(() => {
    if (loadingGeo.value) return 'bg-surface-2 text-surface-400 border-surface-3 cursor-wait';
    if (geoSuccess.value) return 'bg-green-500/10 text-green-500 border-green-500/30 cursor-default';
    return 'bg-primary-500/10 text-primary-400 border-primary-500/30 hover:bg-primary-500/20 active:scale-95';
});

const geoBtnLabel = computed(() => {
    if (loadingGeo.value) return 'Localizando...';
    if (geoSuccess.value) return 'Localização Recebida';
    return 'Usar Localização Atual';
});

watch(() => props.modelValue, (newVal) => {
    if (!newVal) return;
    localData.value = { ...newVal };
    if (newVal.street && (!selectedStreetObj.value || typeof selectedStreetObj.value === 'string')) {
        selectedStreetObj.value = newVal.street;
    }
    isNoNumber.value = (newVal.number === 'S/N');
    if (!newVal.facade_photo) previewImage.value = null;
}, { deep: true });

watch(isFirstAddress, (isFirst) => { if (isFirst) localData.value.is_primary = true; }, { immediate: true });

const toggleNoNumberState = () => { 
    isNoNumber.value = !isNoNumber.value; 
    toggleNoNumber(); 
};

const toggleNoNumber = () => {
    if (isNoNumber.value) {
        localData.value.number = 'S/N';
        determineDistrict('S/N');
    } else {
        localData.value.number = '';
        // Foca no input de número
        setTimeout(() => { if(numberInput.value?.$el) numberInput.value.$el.focus(); }, 100);
    }
    emitUpdate();
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        localData.value.facade_photo = file;
        const reader = new FileReader();
        reader.onload = (e) => previewImage.value = e.target.result;
        reader.readAsDataURL(file);
        emitUpdate();
    }
};

const getLocation = () => {
    if (geoSuccess.value) return; // Já pegou
    if (!navigator.geolocation) { notify('error', 'Erro', 'Geolocalização indisponível.'); return; }
    
    loadingGeo.value = true;
    navigator.geolocation.getCurrentPosition(async (position) => {
        const { latitude, longitude } = position.coords;
        localData.value.coords = { lat: latitude, lng: longitude };
        
        if (window.google && window.google.maps && window.google.maps.Geocoder) {
            const geocoder = new window.google.maps.Geocoder();
            try {
                const response = await geocoder.geocode({ location: { lat: latitude, lng: longitude } });
                if (response.results[0]) {
                    const addr = response.results[0];
                    let street = ''; let number = ''; let district = '';
                    addr.address_components.forEach(c => {
                        if(c.types.includes('route')) street = c.long_name;
                        if(c.types.includes('street_number')) number = c.long_name;
                        if(c.types.includes('sublocality') || c.types.includes('sublocality_level_1')) district = c.long_name;
                    });
                    if(street) { localData.value.street = street; selectedStreetObj.value = street; }
                    if(number) localData.value.number = number;
                    if(district) localData.value.district = district;
                    
                    notify('success', 'Sucesso', 'Endereço encontrado.');
                }
            } catch (e) { notify('warn', 'GPS', 'Localização capturada (Endereço aproximado).'); }
        } else { 
            notify('success', 'GPS', 'Coordenadas salvas.'); 
        }
        
        loadingGeo.value = false;
        geoSuccess.value = true;
        emitUpdate();
    }, (err) => { 
        loadingGeo.value = false; 
        notify('error', 'Erro GPS', 'Permissão negada.'); 
    });
};

const searchStreet = (event) => deliveryStore.searchStreets(event.query);

const onStreetSelect = (event) => {
    const val = event.value;
    localData.value.street = val.name;
    currentSegments.value = val.segments || [];
    determineDistrict(localData.value.number);
    emitUpdate();
    // Pula para número se não for S/N
    setTimeout(() => { if(!isNoNumber.value && numberInput.value?.$el) numberInput.value.$el.focus(); }, 100);
};

const onStreetChange = () => {
    if (typeof selectedStreetObj.value === 'string') {
        localData.value.street = selectedStreetObj.value;
        currentSegments.value = [];
        emitUpdate();
    }
};

const onNumberChange = () => { determineDistrict(localData.value.number); emitUpdate(); };

const determineDistrict = (numberVal) => {
    if (!currentSegments.value || currentSegments.value.length === 0) return;
    if (numberVal === 'S/N' || !numberVal) { localData.value.district = currentSegments.value[0].district; return; }
    const num = parseInt(numberVal);
    if (isNaN(num)) return;
    const match = currentSegments.value.find(seg => num >= seg.min && num <= seg.max);
    localData.value.district = match ? match.district : currentSegments.value[0].district;
};

const emitUpdate = () => {
    if (typeof selectedStreetObj.value === 'string') localData.value.street = selectedStreetObj.value;
    else if (selectedStreetObj.value?.name) localData.value.street = selectedStreetObj.value.name;
    emit('update:modelValue', localData.value);
};

onMounted(() => {
    if (props.modelValue?.street) selectedStreetObj.value = props.modelValue.street;
    if (props.modelValue?.number === 'S/N') isNoNumber.value = true;
});
</script>

<style scoped>
.animate-slide-down { animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>