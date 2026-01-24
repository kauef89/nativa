<template>
  <div class="flex flex-col h-full bg-surface-900 overflow-hidden">
    <div class="p-4 border-b border-surface-800 bg-surface-950 flex justify-between items-center shrink-0">
        <div>
            <h2 class="text-lg font-bold text-white uppercase tracking-wider">Itens da Mesa</h2>
            <p class="text-[10px] text-surface-500 font-bold uppercase tracking-widest">
                Pagando como: <span class="text-primary-400">{{ sessionStore.currentAccount }}</span>
            </p>
        </div>
        <div class="flex gap-2">
            <button class="text-primary-500 text-[10px] font-bold uppercase hover:underline" @click="selectAll">Selecionar Tudo</button>
            <div class="w-px h-3 bg-surface-700 self-center"></div>
            <button class="text-red-400 text-[10px] font-bold uppercase hover:underline" @click="clearSelection">Limpar</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
        
        <div v-if="availableItems.length > 0" class="grid grid-cols-[auto_80px_1fr_90px_70px_90px_80px] gap-3 px-4 pb-2 text-[10px] font-bold text-surface-500 uppercase tracking-wider">
            <span></span> <span class="text-center">Qtd / Parte</span>
            <span>Item / Modif.</span>
            <span class="text-right">Unitário</span>
            <span class="text-center">Divisão</span>
            <span class="text-right">Total</span>
            <span class="text-center">Ações</span>
        </div>

        <div class="space-y-2">
            <div 
                v-for="item in availableItems" 
                :key="item.id"
                class="bg-surface-950 border border-surface-800 rounded-xl p-3 grid grid-cols-[auto_80px_1fr_90px_70px_90px_80px] gap-3 items-center transition-all hover:border-surface-600"
                :class="{'border-primary-500/50 bg-primary-500/5': isSelected(item.id)}"
            >
                <Checkbox 
                    :model-value="isSelected(item.id)" 
                    @update:model-value="(val) => toggleItem(item, val)"
                    :binary="true"
                    class="shrink-0"
                />

                <div>
                    <Dropdown 
                        v-if="itemStates[item.id]"
                        v-model="itemStates[item.id].selectedQty" 
                        :options="getSmartOptions(item)" 
                        optionLabel="label"
                        optionValue="value"
                        class="w-full !h-8 text-xs"
                        :pt="{
                            root: { class: '!bg-surface-900 !border-surface-700 !rounded-lg' },
                            input: { class: '!py-1.5 !px-2 !text-xs !font-bold !text-center' },
                            trigger: { class: '!w-6' },
                            panel: { class: '!bg-surface-900 !border-surface-700' },
                            item: { class: '!text-xs !p-2 hover:!bg-surface-800 !text-surface-200' }
                        }"
                        @change="updateStoreValue(item)"
                    />
                </div>

                <div class="min-w-0 flex flex-col justify-center overflow-hidden">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-white leading-none whitespace-nowrap">{{ item.name }}</span>
                        
                        <span v-if="item.sub_account === 'Principal' && sessionStore.currentAccount !== 'Principal'" 
                              class="text-[9px] bg-surface-800 text-surface-400 px-1.5 py-0.5 rounded border border-surface-700 uppercase font-bold tracking-wider">
                            Mesa
                        </span>
                        <span v-else-if="item.sub_account !== 'Principal'" 
                              class="text-[9px] bg-primary-500/10 text-primary-400 px-1.5 py-0.5 rounded border border-primary-500/20 uppercase font-bold tracking-wider">
                            {{ item.sub_account }}
                        </span>
                    </div>
                    
                    <div v-if="item.modifiers?.length" class="flex items-center gap-1 text-[10px] text-surface-400 italic truncate border-l border-surface-700 pl-2 mt-1">
                        <span v-for="(mod, i) in item.modifiers" :key="i">
                            +{{ mod.name }}<span v-if="i < item.modifiers.length - 1">,</span>
                        </span>
                    </div>
                </div>

                <div class="text-right font-mono text-xs text-surface-400">
                    {{ formatMoney(getItemUnitPrice(item)) }}
                </div>

                <div class="flex justify-center">
                    <InputNumber 
                        v-if="itemStates[item.id]"
                        v-model="itemStates[item.id].division" 
                        :min="1" 
                        :max="100" 
                        showButtons
                        buttonLayout="horizontal"
                        class="!w-16 h-8"
                        inputClass="!w-full !text-center !p-0 !bg-surface-900 !border-surface-700 !text-white !text-xs !font-bold !h-8"
                        decrementButtonClass="!w-4 !bg-surface-800 !text-surface-400 !border-surface-700 !p-0"
                        incrementButtonClass="!w-4 !bg-surface-800 !text-surface-400 !border-surface-700 !p-0"
                        @input="handleDivisionChange(item)"
                    />
                </div>

                <div class="text-right font-mono font-bold text-sm" :class="isSelected(item.id) ? 'text-white' : 'text-surface-500'">
                    {{ formatMoney(calculateFinalValue(item)) }}
                </div>

                <div class="flex justify-center gap-1">
                    <Button 
                        icon="fa-solid fa-arrow-right-arrow-left" 
                        text rounded 
                        class="!w-8 !h-8 !text-blue-400 hover:!bg-blue-500/10" 
                        v-tooltip.top="'Trocar Item'"
                        @click.stop="$emit('request-swap', item)"
                    />
                    <Button 
                        icon="fa-solid fa-trash-can" 
                        text rounded 
                        class="!w-8 !h-8 !text-red-400 hover:!bg-red-500/10" 
                        v-tooltip.top="'Cancelar Item'"
                        @click.stop="$emit('request-cancel', item)"
                    />
                </div>
            </div>

            <div v-if="availableItems.length === 0" class="text-center py-10 text-surface-600">
                <i class="fa-solid fa-receipt text-3xl mb-2 opacity-20"></i>
                <p class="text-sm">Nenhum item disponível para pagamento.</p>
            </div>
        </div>

        <div v-if="sessionStore.paidItems.length > 0" class="mt-8 border-t border-surface-800 pt-6 pb-10">
             <div class="flex items-center gap-3 px-1 mb-4 opacity-50">
                <i class="fa-solid fa-circle-check text-green-500 text-xs"></i>
                <span class="text-[10px] font-black text-surface-400 uppercase tracking-[0.2em]">Itens Quitados / Pagos</span>
                <div class="h-px bg-surface-800 flex-1"></div>
            </div>

            <div class="space-y-2 opacity-60">
                <div v-for="item in sessionStore.paidItems" :key="item.id" class="bg-surface-950/30 border border-surface-800/50 rounded-xl p-2 px-4 flex justify-between items-center text-xs">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-surface-500">{{ item.qty }}x</span>
                        <span class="text-surface-400">{{ item.name }}</span>
                        <span class="text-[10px] bg-surface-800 px-1.5 rounded text-surface-500 border border-surface-700">{{ item.sub_account }}</span>
                    </div>
                    <span class="font-bold text-green-500/70 border border-green-500/20 px-1.5 rounded bg-green-500/5 text-[9px]">PAGO</span>
                </div>
            </div>
        </div>

    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import Checkbox from 'primevue/checkbox';
import InputNumber from 'primevue/inputnumber';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';

const emit = defineEmits(['request-swap', 'request-cancel']);
const sessionStore = useSessionStore();
const itemStates = ref({});

// --- COMPUTEDS E HELPER FUNCTIONS (DEFINIDOS PRIMEIRO) ---

const availableItems = computed(() => {
    let items = sessionStore.items.filter(i => i.status !== 'paid' && i.status !== 'cancelled');
    if (sessionStore.currentAccount && sessionStore.currentAccount !== 'Principal') {
        items = items.filter(i => i.sub_account === sessionStore.currentAccount || i.sub_account === 'Principal');
    } else {
        items = items.filter(i => i.sub_account === 'Principal');
    }
    return items;
});

const isSelected = (id) => sessionStore.selectedItemsForPayment.some(i => i.itemId === id);

const getQtyOptions = (maxQty) => Array.from({ length: maxQty }, (_, i) => i + 1);
const getItemUnitPrice = (item) => parseFloat(item.price || 0);

const getSmartOptions = (item) => {
    const state = itemStates.value[item.id];
    if (!state) return [];
    if (state.division > 1) {
        return Array.from({ length: state.division }, (_, i) => {
            const parts = i + 1;
            return {
                label: parts === state.division ? `${parts}/${state.division} (Inteiro)` : `${parts}/${state.division}`,
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

const formatMoney = (val) => 'R$ ' + (parseFloat(val) || 0).toFixed(2).replace('.', ',');

// --- WATCHER MOVIDO PARA O FINAL (EVITA REFERENCE ERROR) ---
watch(availableItems, (items) => {
    items.forEach(item => {
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
            if (!isSelected(item.id) && item.sub_account === sessionStore.currentAccount) {
                addToSelection(item);
            }
        }
    });
}, { immediate: true, deep: true });

onMounted(() => {
    if (sessionStore.currentAccount === 'Principal') selectAll();
});
</script>