<template>
  <div class="flex flex-col h-full bg-surface-2 overflow-hidden">
    
    <div class="p-4 border-b border-surface-3/10 flex justify-between items-center shrink-0">
        <div>
            <h2 class="text-sm font-black text-surface-0 uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-list-check text-primary-500"></i> Itens da Conta
            </h2>
            <p class="text-[10px] text-surface-500 font-bold uppercase tracking-widest mt-0.5">
                Titular: <span class="text-surface-0">{{ sessionStore.currentAccount }}</span>
            </p>
        </div>
        <div class="flex gap-3 bg-surface-1 px-3 py-1.5 rounded-full border border-surface-3/10">
            <button class="text-primary-500 text-[10px] font-black uppercase hover:text-primary-400" @click="selectAll">Todos</button>
            <div class="w-px h-3 bg-surface-3 self-center"></div>
            <button class="text-surface-400 text-[10px] font-black uppercase hover:text-surface-0" @click="clearSelection">Nenhum</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
        
        <div v-if="availableItems.length > 0" class="grid grid-cols-[auto_80px_1fr_90px_70px_90px_80px] gap-3 px-4 pb-2 text-[10px] font-black text-surface-500 uppercase tracking-wider">
            <span></span> <span class="text-center">Qtd</span>
            <span>Descrição</span>
            <span class="text-right">Unit.</span>
            <span class="text-center">Divisão</span>
            <span class="text-right">Total</span>
            <span class="text-center">Ações</span>
        </div>

        <div class="space-y-2">
            <div 
                v-for="item in availableItems" 
                :key="item.id"
                class="bg-surface-1 border border-transparent rounded-[16px] p-2 grid grid-cols-[auto_80px_1fr_90px_70px_90px_80px] gap-3 items-center transition-all hover:bg-surface-3 group"
                :class="{'!bg-primary-500/10 !border-primary-500/30': isSelected(item.id)}"
            >
                <Checkbox 
                    :model-value="isSelected(item.id)" 
                    @update:model-value="(val) => toggleItem(item, val)"
                    :binary="true"
                    class="shrink-0 ml-2"
                />

                <div>
                    <Select 
                        v-if="itemStates[item.id]"
                        v-model="itemStates[item.id].selectedQty" 
                        :options="getSmartOptions(item)" 
                        optionLabel="label"
                        optionValue="value"
                        class="w-full !h-8 text-xs !rounded-lg !bg-surface-2 !border-none"
                        :pt="{ label: { class: '!text-surface-0 !font-bold !p-1' } }"
                        @change="updateStoreValue(item)"
                    />
                </div>

                <div class="min-w-0 flex flex-col justify-center overflow-hidden">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-surface-0 leading-none whitespace-nowrap text-sm">{{ item.name }}</span>
                    </div>
                    <div v-if="item.modifiers?.length" class="flex items-center gap-1 text-[10px] text-surface-400 italic truncate pl-1 mt-0.5">
                        <span v-for="(mod, i) in item.modifiers" :key="i">
                            +{{ mod.name }}<span v-if="i < item.modifiers.length - 1">,</span>
                        </span>
                    </div>
                </div>

                <div class="text-right text-xs text-surface-400 font-bold">
                    {{ formatCurrency(getItemUnitPrice(item)) }}
                </div>

                <div class="flex justify-center">
                    <InputNumber 
                        v-if="itemStates[item.id]"
                        v-model="itemStates[item.id].division" 
                        :min="1" :max="100" 
                        showButtons buttonLayout="horizontal"
                        class="!w-20 h-8"
                        inputClass="!w-full !text-center !p-0 !text-surface-0 !text-xs !font-bold !bg-transparent !border-0"
                        decrementButtonClass="!w-5 !bg-surface-2 !text-surface-400 !border-none hover:!bg-surface-3 hover:!text-surface-0 !rounded-l-lg"
                        incrementButtonClass="!w-5 !bg-surface-2 !text-surface-400 !border-none hover:!bg-surface-3 hover:!text-surface-0 !rounded-r-lg"
                        @input="handleDivisionChange(item)"
                    />
                </div>

                <div class="text-right font-black text-sm" :class="isSelected(item.id) ? 'text-primary-400' : 'text-surface-500'">
                    {{ formatCurrency(calculateFinalValue(item)) }}
                </div>

                <div class="flex justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <Button icon="fa-solid fa-pen" text rounded class="!w-8 !h-8 !text-surface-400 hover:!text-surface-0" @click.stop="handleEditClick(item)" />
                    <Button icon="fa-solid fa-trash-can" text rounded class="!w-8 !h-8 !text-red-400 hover:!bg-red-500/10" @click.stop="$emit('request-cancel', item)" />
                </div>
            </div>

            <div v-if="availableItems.length === 0" class="text-center py-20 text-surface-600 select-none opacity-50">
                <i class="fa-solid fa-receipt text-4xl mb-3"></i>
                <p class="text-sm font-bold uppercase">Nenhum item nesta conta.</p>
            </div>
        </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useFormat } from '@/composables/useFormat';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Button from 'primevue/button';

const emit = defineEmits(['request-swap', 'request-cancel', 'request-edit']);
const sessionStore = useSessionStore();
const { formatCurrency } = useFormat(); 
const itemStates = ref({});

const handleEditClick = (item) => {
    emit('request-edit', item);
};

const availableItems = computed(() => {
    let items = sessionStore.items.filter(i => i.status !== 'paid' && i.status !== 'cancelled');
    const targetAccount = sessionStore.currentAccount || 'Principal';
    return items.filter(i => i.sub_account === targetAccount);
});

const isSelected = (id) => sessionStore.selectedItemsForPayment.some(i => i.itemId === id);

const getItemUnitPrice = (item) => parseFloat(item.price || 0);

const getSmartOptions = (item) => {
    const state = itemStates.value[item.id];
    if (!state) return [];
    if (state.division > 1) {
        return Array.from({ length: state.division }, (_, i) => {
            const parts = i + 1;
            return {
                label: parts === state.division ? `${parts}/${state.division} (Total)` : `${parts}/${state.division}`,
                value: parts
            };
        });
    }
    return Array.from({ length: item.qty }, (_, i) => ({
        label: `${i + 1}x`,
        value: i + 1
    }));
};

const calculateFinalValue = (item) => {
    const state = itemStates.value[item.id];
    if (!state) return 0;
    const unitPrice = getItemUnitPrice(item);
    if (state.division > 1) {
        const totalLineValue = unitPrice * item.qty;
        return (totalLineValue / state.division) * state.selectedQty;
    } else {
        return unitPrice * state.selectedQty;
    }
};

const updateStoreValue = (item) => {
    if (!isSelected(item.id)) return;
    const finalValue = calculateFinalValue(item);
    const idx = sessionStore.selectedItemsForPayment.findIndex(i => i.itemId === item.id);
    if (idx > -1) sessionStore.selectedItemsForPayment[idx].valueToPay = finalValue;
};

const handleDivisionChange = (item) => {
    const state = itemStates.value[item.id];
    if (!state) return;
    if (state.division > 1) {
        state.selectedQty = 1; 
    } else {
        state.selectedQty = item.qty; 
    }
    updateStoreValue(item);
};

const addToSelection = (item) => {
    const val = calculateFinalValue(item);
    sessionStore.selectedItemsForPayment.push({ itemId: item.id, valueToPay: val });
};

const removeFromSelection = (item) => {
    const idx = sessionStore.selectedItemsForPayment.findIndex(i => i.itemId === item.id);
    if (idx > -1) sessionStore.selectedItemsForPayment.splice(idx, 1);
};

const toggleItem = (item, isChecked) => {
    isChecked ? addToSelection(item) : removeFromSelection(item);
};

const selectAll = () => {
    sessionStore.selectedItemsForPayment = [];
    availableItems.value.forEach(item => addToSelection(item));
};

const clearSelection = () => sessionStore.selectedItemsForPayment = [];

watch(availableItems, (newItems) => {
    const visibleIds = newItems.map(i => i.id);
    sessionStore.selectedItemsForPayment = sessionStore.selectedItemsForPayment.filter(
        selection => visibleIds.includes(selection.itemId)
    );

    newItems.forEach(item => {
        const currentDebt = getItemUnitPrice(item) * item.qty;
        
        if (itemStates.value[item.id]) {
            const state = itemStates.value[item.id];
            if (state.lastKnownTotal && Math.abs(currentDebt - state.lastKnownTotal) > 0.01) {
                state.division = 1;
                state.selectedQty = item.qty; 
            }
            state.lastKnownTotal = currentDebt;
            nextTick(() => updateStoreValue(item));
        } else {
            itemStates.value[item.id] = { 
                selectedQty: item.qty, 
                division: 1,
                lastKnownTotal: currentDebt
            };
            if (!isSelected(item.id)) {
                addToSelection(item);
            }
        }
    });
}, { immediate: true, deep: true });

onMounted(() => {
    if (availableItems.value.length > 0) selectAll();
});
</script>