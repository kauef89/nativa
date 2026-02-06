<template>
  <div class="flex flex-col gap-6 animate-fade-in">
    
    <div class="bg-surface-2 p-5 rounded-[20px] border border-surface-3/10">
        <div class="text-[10px] font-black text-surface-400 uppercase mb-4 flex items-center gap-2 tracking-widest">
            <i class="fa-solid fa-money-bill text-green-500"></i> Cédulas
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-7 gap-3">
            <div v-for="denom in localBills" :key="'bill-'+denom.val" 
                 class="flex flex-col items-center bg-surface-1 p-2 rounded-2xl border border-surface-3/5 focus-within:border-primary-500/50 focus-within:ring-2 focus-within:ring-primary-500/20 transition-all">
                <span class="text-sm font-black text-surface-200 mb-1 tracking-tighter">R$ {{ denom.val }}</span>
                <InputNumber 
                    v-model="denom.qty" 
                    :min="0" 
                    :step="1" 
                    showButtons 
                    buttonLayout="vertical" 
                    inputClass="nativa-cash-input !w-full !text-center !p-1 !text-base !font-black !bg-transparent !text-surface-0 !border-none !shadow-none focus:!ring-0" 
                    class="w-full"
                    decrementButtonClass="!bg-surface-2 !text-surface-400 hover:!bg-surface-3 !h-5 !w-full !border-none !rounded-t-lg !flex !items-center !justify-center !text-[10px]" 
                    incrementButtonClass="!bg-surface-2 !text-surface-400 hover:!bg-surface-3 !h-5 !w-full !border-none !rounded-b-lg !flex !items-center !justify-center !text-[10px]"
                    @input="emitChange"
                    @keydown.enter.prevent="focusNext($event)"
                />
            </div>
        </div>
        <div class="mt-4 flex justify-between items-center px-2">
            <span class="text-[10px] uppercase font-bold text-surface-500">Total Cédulas</span>
            <span class="text-lg font-black text-surface-200">{{ formatCurrency(billsTotal) }}</span>
        </div>
    </div>

    <div class="bg-surface-2 p-5 rounded-[20px] border border-surface-3/10">
        <div class="text-[10px] font-black text-surface-400 uppercase mb-4 flex items-center gap-2 tracking-widest">
            <i class="fa-solid fa-coins text-yellow-500"></i> Moedas
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
            <div v-for="denom in localCoins" :key="'coin-'+denom.val" 
                 class="flex flex-col items-center bg-surface-1 p-2 rounded-2xl border border-surface-3/5 focus-within:border-primary-500/50 focus-within:ring-2 focus-within:ring-primary-500/20 transition-all">
                <span class="text-sm font-black text-surface-200 mb-1 tracking-tighter">{{ formatCurrency(denom.val) }}</span>
                <InputNumber 
                    v-model="denom.qty" 
                    :min="0" 
                    :step="1" 
                    showButtons 
                    buttonLayout="vertical" 
                    inputClass="nativa-cash-input !w-full !text-center !p-1 !text-base !font-black !bg-transparent !text-surface-0 !border-none !shadow-none focus:!ring-0" 
                    class="w-full"
                    decrementButtonClass="!bg-surface-2 !text-surface-400 hover:!bg-surface-3 !h-5 !w-full !border-none !rounded-t-lg !flex !items-center !justify-center !text-[10px]" 
                    incrementButtonClass="!bg-surface-2 !text-surface-400 hover:!bg-surface-3 !h-5 !w-full !border-none !rounded-b-lg !flex !items-center !justify-center !text-[10px]"
                    @input="emitChange"
                    @keydown.enter.prevent="focusNext($event)"
                />
            </div>
        </div>
        <div class="mt-4 flex justify-between items-center px-2">
            <span class="text-[10px] uppercase font-bold text-surface-500">Total Moedas</span>
            <span class="text-lg font-black text-surface-200">{{ formatCurrency(coinsTotal) }}</span>
        </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useFormat } from '@/composables/useFormat';
import InputNumber from 'primevue/inputnumber';

const props = defineProps(['modelValue']); // { bills: [], coins: [] }
const emit = defineEmits(['update:modelValue', 'change']);
const { formatCurrency } = useFormat();

// Estrutura Padrão Garantida
const defaultBills = [
    { val: 200, qty: 0 }, { val: 100, qty: 0 }, { val: 50, qty: 0 }, 
    { val: 20, qty: 0 }, { val: 10, qty: 0 }, { val: 5, qty: 0 }, { val: 2, qty: 0 }
];

const defaultCoins = [
    { val: 1, qty: 0 }, { val: 0.50, qty: 0 }, { val: 0.25, qty: 0 }, 
    { val: 0.10, qty: 0 }, { val: 0.05, qty: 0 }
];

const localBills = ref(JSON.parse(JSON.stringify(defaultBills)));
const localCoins = ref(JSON.parse(JSON.stringify(defaultCoins)));

const init = () => {
    // Se vier dados do pai, mescla com a estrutura padrão para garantir que todos os campos existam
    if (props.modelValue && Array.isArray(props.modelValue.bills) && props.modelValue.bills.length > 0) {
        // Mapeia os valores existentes para a estrutura local
        localBills.value = defaultBills.map(def => {
            const found = props.modelValue.bills.find(b => b.val === def.val);
            return found ? { ...found } : { ...def };
        });
    } else {
        localBills.value = JSON.parse(JSON.stringify(defaultBills));
    }

    if (props.modelValue && Array.isArray(props.modelValue.coins) && props.modelValue.coins.length > 0) {
        localCoins.value = defaultCoins.map(def => {
            const found = props.modelValue.coins.find(c => c.val === def.val);
            return found ? { ...found } : { ...def };
        });
    } else {
        localCoins.value = JSON.parse(JSON.stringify(defaultCoins));
    }
};

watch(() => props.modelValue, () => {
    // Só reinicia se os dados locais estiverem vazios ou zerados e vier dado novo,
    // para evitar sobrescrever o que o usuário está digitando se o pai atualizar por algum motivo
    // Mas para garantir sincronia inicial:
    // init(); 
}, { deep: true });

onMounted(() => {
    init();
    // Foca no primeiro input automaticamente
    nextTick(() => {
        const firstInput = document.querySelector('.nativa-cash-input');
        if(firstInput) firstInput.focus();
    });
});

const billsTotal = computed(() => localBills.value.reduce((acc, b) => acc + (b.val * b.qty), 0));
const coinsTotal = computed(() => localCoins.value.reduce((acc, c) => acc + (c.val * c.qty), 0));
const total = computed(() => billsTotal.value + coinsTotal.value);

const emitChange = () => {
    const payload = {
        bills: localBills.value,
        coins: localCoins.value
    };
    emit('update:modelValue', payload);
    emit('change', { total: total.value, breakdown: payload });
};

// Navegação por Enter
const focusNext = (event) => {
    const inputs = document.querySelectorAll('.nativa-cash-input');
    const inputsArray = Array.from(inputs);
    const currentIndex = inputsArray.indexOf(event.target);

    if (currentIndex !== -1 && currentIndex < inputsArray.length - 1) {
        const nextInput = inputsArray[currentIndex + 1];
        nextInput.focus();
        nextInput.select(); // Seleciona o conteúdo para facilitar a sobrescrita
    } else {
        event.target.blur(); // Sai do campo se for o último
    }
};
</script>