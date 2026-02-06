<template>
  <Dialog 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    modal 
    header="Seleção de Impressão" 
    :style="{ width: '450px' }"
    :pt="{
        root: { class: '!bg-surface-1 !border-none !rounded-[24px] !shadow-2xl' },
        header: { class: '!bg-surface-2 !border-b !border-surface-3/10 !py-4 !px-6 !rounded-t-[24px]' },
        content: { class: '!bg-surface-1 !p-0' },
        footer: { class: '!bg-surface-1 !border-t !border-surface-3/10 !p-4 !rounded-b-[24px]' }
    }"
  >
    <template #header>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-surface-3 flex items-center justify-center text-surface-400">
                <i class="fa-solid fa-print"></i>
            </div>
            <div>
                <h3 class="text-lg font-black text-surface-0 leading-tight">Reimprimir / Redirecionar</h3>
                <p class="text-xs text-surface-400 font-bold">Pedido #{{ order?.id }}</p>
            </div>
        </div>
    </template>

    <div class="flex flex-col max-h-[60vh]">
        
        <div class="p-4 bg-surface-2/30 border-b border-surface-3/10">
            <label class="text-[10px] font-black uppercase text-surface-500 tracking-widest mb-2 block">Enviar Para:</label>
            <div class="flex gap-2">
                <button 
                    v-for="st in stations" :key="st.id"
                    class="flex-1 py-2 px-3 rounded-lg text-xs font-bold border transition-all flex items-center justify-center gap-2"
                    :class="selectedStation === st.id 
                        ? 'bg-primary-500 text-surface-950 border-primary-500 shadow-md' 
                        : 'bg-surface-1 text-surface-400 border-surface-3/50 hover:bg-surface-3 hover:text-surface-200'"
                    @click="selectedStation = st.id"
                >
                    <i :class="st.icon"></i> {{ st.label }}
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between px-4 py-2 bg-surface-2/50 border-b border-surface-3/10">
            <span class="text-[10px] font-black uppercase text-surface-500 tracking-widest">
                {{ selectedItems.length }} itens selecionados
            </span>
            <div class="flex gap-2">
                <Button label="Todos" size="small" text class="!text-[10px] !h-6 !px-2" @click="selectAll" />
                <Button label="Nenhum" size="small" text class="!text-[10px] !h-6 !px-2 !text-surface-400" @click="selectedItems = []" />
            </div>
        </div>

        <div class="overflow-y-auto p-2 space-y-1 scrollbar-thin">
            <div 
                v-for="(item, index) in orderItems" 
                :key="index"
                class="flex items-start gap-3 p-3 rounded-xl cursor-pointer transition-colors border border-transparent hover:border-surface-3/30"
                :class="isSelected(item) ? 'bg-primary-500/10' : 'bg-surface-1 hover:bg-surface-2'"
                @click="toggleItem(item)"
            >
                <Checkbox :modelValue="isSelected(item)" :binary="true" readonly class="mt-1" />
                
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <span class="font-bold text-sm text-surface-0 leading-tight" :class="{'text-primary-400': isSelected(item)}">
                            {{ item.qty }}x {{ item.name }}
                        </span>
                    </div>
                    <div v-if="item.modifiers && item.modifiers.length" class="pl-2 mt-1 border-l-2 border-surface-3/30 space-y-0.5">
                        <div v-for="(mod, mIdx) in item.modifiers" :key="mIdx" class="text-[10px] text-surface-400">
                            + {{ mod.name }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template #footer>
        <div class="flex justify-end gap-3 w-full">
            <Button label="Cancelar" text class="!text-surface-400 hover:!text-surface-0 !font-bold" @click="$emit('update:visible', false)" />
            <Button 
                :label="printButtonLabel" 
                icon="fa-solid fa-print" 
                class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-black !text-surface-950 !rounded-full shadow-lg" 
                :disabled="selectedItems.length === 0"
                :loading="loading"
                @click="handlePrint"
            />
        </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Checkbox from 'primevue/checkbox';

const props = defineProps(['visible', 'order', 'loading']);
const emit = defineEmits(['update:visible', 'confirm']);

const selectedItems = ref([]);
const selectedStation = ref('original'); // 'original', 'frente', 'fundos'

const stations = [
    { id: 'original', label: 'Original (Auto)', icon: 'fa-solid fa-wand-magic-sparkles' },
    { id: 'frente', label: 'Frente (Caixa)', icon: 'fa-solid fa-store' },
    { id: 'fundos', label: 'Fundos (Cozinha)', icon: 'fa-solid fa-fire-burner' }
];

const orderItems = computed(() => {
    return Array.isArray(props.order?.items) ? props.order.items : [];
});

const printButtonLabel = computed(() => {
    if (selectedStation.value === 'original') return 'REIMPRIMIR';
    return `ENVIAR P/ ${selectedStation.value.toUpperCase()}`;
});

watch(() => props.visible, (val) => {
    if (val) {
        selectAll();
        selectedStation.value = 'original'; // Reset para padrão
    }
});

const isSelected = (item) => selectedItems.value.includes(item);

const toggleItem = (item) => {
    if (isSelected(item)) {
        selectedItems.value = selectedItems.value.filter(i => i !== item);
    } else {
        selectedItems.value.push(item);
    }
};

const selectAll = () => {
    selectedItems.value = [...orderItems.value];
};

const handlePrint = () => {
    // Cria cópia do pedido
    const partialOrder = {
        ...props.order,
        items: JSON.parse(JSON.stringify(selectedItems.value)),
        // Envia o override de estação
        targetStation: selectedStation.value === 'original' ? null : selectedStation.value
    };
    emit('confirm', partialOrder);
};
</script>