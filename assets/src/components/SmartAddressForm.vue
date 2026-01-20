<template>
  <div class="grid grid-cols-12 gap-3 animate-fade-in">
    
    <div class="col-span-12 md:col-span-9 flex flex-col gap-2">
        <label class="text-sm font-bold text-surface-400">Rua / Logradouro</label>
        <AutoComplete 
            v-model="selectedStreetObj" 
            :suggestions="deliveryStore.streetSuggestions" 
            optionLabel="name" 
            placeholder="Digite para buscar..." 
            class="w-full"
            @complete="searchStreet" 
            @item-select="onStreetSelect"
            @change="onStreetChange"
            :pt="{ 
                root: { class: 'w-full' }, 
                input: { class: '!bg-surface-950 !border-surface-700 !w-full !text-white !h-11' }, 
                panel: { class: '!bg-surface-900 !border-surface-700' } 
            }"
        >
            <template #option="slotProps">
                <div class="flex items-center w-full p-2">
                    <i class="fa-solid fa-location-dot mr-3 text-surface-400"></i>
                    <div class="flex flex-col">
                        <span class="font-bold text-white">{{ slotProps.option.name }}</span>
                        <span v-if="slotProps.option.district" class="text-xs text-surface-500">
                            {{ slotProps.option.district }} 
                            <span v-if="slotProps.option.segments?.length > 1" class="text-primary-500 ml-1">(Múltiplos)</span>
                        </span>
                    </div>
                </div>
            </template>
        </AutoComplete>
    </div>

    <div class="col-span-5 md:col-span-3 flex flex-col gap-2">
        <label class="text-sm font-bold text-surface-400">Número</label>
        <div class="flex items-center gap-2 h-11">
            <InputText 
                v-model="localData.number" 
                placeholder="Nº"
                class="!bg-surface-950 !border-surface-700 !text-white flex-1 min-w-0 transition-colors !h-full text-center"
                :class="{'!bg-surface-800 !text-surface-500 cursor-not-allowed': isNoNumber}"
                :disabled="isNoNumber"
                @update:modelValue="onNumberChange"
                ref="numberInput"
            />
            
            <div class="flex flex-col items-center justify-center shrink-0" title="Endereço sem número">
                <ToggleSwitch v-model="isNoNumber" @change="toggleNoNumber" :pt="{ root: { class: '!w-8 !h-4' }, slider: { class: '!bg-surface-600' } }" />
                <span class="text-[9px] font-bold text-surface-500 mt-0.5 uppercase tracking-wider cursor-pointer select-none" @click="isNoNumber = !isNoNumber; toggleNoNumber()">S/N</span>
            </div>
        </div>
    </div>

    <div class="col-span-7 md:col-span-3 flex flex-col gap-2">
        <label class="text-sm font-bold text-surface-400">Bairro</label>
        <InputText 
            v-model="localData.district" 
            class="!bg-surface-800 !border-surface-700 !text-surface-300 cursor-not-allowed focus:!ring-0 !h-11 truncate"
            readonly
            tabindex="-1"
            placeholder="Auto..."
        />
    </div>

    <div class="col-span-12 md:col-span-9 flex flex-col gap-2">
        <label class="text-sm font-bold text-surface-400 flex items-center justify-between">
            <span>Complemento / Referência</span>
            <span v-if="isNoNumber" class="text-primary-500 text-[10px] uppercase font-bold animate-pulse"><i class="fa-solid fa-asterisk text-[8px] mr-1"></i>Obrigatório</span>
        </label>
        <InputText 
            v-model="localData.reference" 
            placeholder="Ex: Apto 101, Ao lado da padaria"
            class="!bg-surface-950 !border-surface-700 !text-white transition-all !h-11"
            :class="{'!border-red-500 !ring-1 !ring-red-500/50': showReferenceError}"
            @update:modelValue="emitUpdate"
        />
    </div>

  </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { useDeliveryStore } from '@/stores/delivery-store';
import AutoComplete from 'primevue/autocomplete';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ street: '', number: '', district: '', reference: '' }) }
});
const emit = defineEmits(['update:modelValue']);
const deliveryStore = useDeliveryStore();

// CORREÇÃO 1: Garante que localData nunca seja null/undefined ao iniciar
const localData = ref(props.modelValue ? { ...props.modelValue } : { street: '', number: '', district: '', reference: '' });

const selectedStreetObj = ref(null);
const currentSegments = ref([]); 
const numberInput = ref(null);
const isNoNumber = ref(false);

const showReferenceError = computed(() => isNoNumber.value && (!localData.value.reference || localData.value.reference.length < 3));

watch(() => props.modelValue, (newVal) => {
    // CORREÇÃO 2: Proteção contra null no watcher
    if (!newVal) {
        localData.value = { street: '', number: '', district: '', reference: '' };
        return;
    }
    
    localData.value = { ...newVal };
    if (newVal.street && (!selectedStreetObj.value || typeof selectedStreetObj.value === 'string')) {
        selectedStreetObj.value = newVal.street;
    }
    isNoNumber.value = (newVal.number === 'S/N');
}, { deep: true });

const toggleNoNumber = () => {
    if (isNoNumber.value) {
        localData.value.number = 'S/N';
        determineDistrict('S/N');
    } else {
        localData.value.number = '';
        setTimeout(() => { if(numberInput.value?.$el) numberInput.value.$el.focus(); }, 100);
    }
    emitUpdate();
};

const searchStreet = (event) => deliveryStore.searchStreets(event.query);

const onStreetSelect = (event) => {
    const val = event.value;
    localData.value.street = val.name;
    currentSegments.value = val.segments || [];
    determineDistrict(localData.value.number);
    emitUpdate();
    setTimeout(() => { if(!isNoNumber.value && numberInput.value?.$el) numberInput.value.$el.focus(); }, 100);
};

const onStreetChange = () => {
    if (typeof selectedStreetObj.value === 'string') {
        localData.value.street = selectedStreetObj.value;
        currentSegments.value = [];
        emitUpdate();
    }
};

const onNumberChange = () => {
    determineDistrict(localData.value.number);
    emitUpdate();
};

const determineDistrict = (numberVal) => {
    if (!currentSegments.value || currentSegments.value.length === 0) return;

    if (numberVal === 'S/N' || !numberVal) {
        localData.value.district = currentSegments.value[0].district;
        return;
    }

    const num = parseInt(numberVal);
    if (isNaN(num)) return;

    const match = currentSegments.value.find(seg => num >= seg.min && num <= seg.max);
    localData.value.district = match ? match.district : currentSegments.value[0].district;
};

const emitUpdate = () => {
    if (typeof selectedStreetObj.value === 'string') {
        localData.value.street = selectedStreetObj.value;
    } else if (selectedStreetObj.value?.name) {
        localData.value.street = selectedStreetObj.value.name;
    }
    emit('update:modelValue', localData.value);
};

onMounted(() => {
    // CORREÇÃO 3: Optional Chaining (?.street) para não quebrar se for null
    if (props.modelValue?.street) selectedStreetObj.value = props.modelValue.street;
    if (props.modelValue?.number === 'S/N') isNoNumber.value = true;
});
</script>