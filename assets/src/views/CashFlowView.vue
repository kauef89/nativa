<template>
  <div class="p-6 w-full h-full flex flex-col bg-surface-950 overflow-hidden">
    
    <div class="flex flex-col gap-6 shrink-0 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                    <i class="fa-solid fa-cash-register text-primary-500"></i> Fluxo de Caixa
                </h1>
                <div v-if="store.isOpen" class="text-sm text-green-400 font-bold mt-1 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    Caixa Aberto desde {{ formatDate(store.register?.opened_at) }}
                </div>
                <div v-else class="text-sm text-red-400 font-bold mt-1 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    Caixa Fechado
                </div>
            </div>

            <div class="flex gap-3">
                <Button 
                    label="Operações (Sangria/Suprimento)" 
                    icon="fa-solid fa-sliders" 
                    class="!bg-surface-800 !border-surface-700 hover:!bg-surface-700 !text-white"
                    @click="showOperationsModal = true"
                    :disabled="!store.isOpen"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl shadow-lg relative overflow-hidden group">
                <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i class="fa-solid fa-money-bill-wave text-5xl text-green-500"></i>
                </div>
                <div class="text-surface-400 text-xs font-bold uppercase tracking-wider mb-1">Na Gaveta (Dinheiro)</div>
                <div class="text-3xl font-mono font-bold text-green-400">R$ {{ formatMoney(store.summary.cash_balance) }}</div>
            </div>

            <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl shadow-lg">
                <div class="text-surface-400 text-xs font-bold uppercase tracking-wider mb-1">Faturamento Total</div>
                <div class="text-3xl font-mono font-bold text-white">R$ {{ formatMoney(store.summary.total_in) }}</div>
                <div class="text-xs text-surface-500 mt-1">Inclui Cartões e Pix</div>
            </div>

            <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl shadow-lg">
                <div class="text-surface-400 text-xs font-bold uppercase tracking-wider mb-1">Saídas / Sangrias</div>
                <div class="text-3xl font-mono font-bold text-red-400">- R$ {{ formatMoney(store.summary.total_out) }}</div>
            </div>

            <div class="bg-surface-900 border border-surface-800 p-5 rounded-2xl flex flex-col justify-center items-center cursor-pointer hover:bg-surface-800 transition-colors"
                 @click="handleMainAction">
                <i class="fa-solid text-3xl mb-2" :class="store.isOpen ? 'fa-lock text-red-500' : 'fa-lock-open text-green-500'"></i>
                <span class="font-bold text-sm text-white">{{ store.isOpen ? 'FECHAR CAIXA' : 'ABRIR CAIXA' }}</span>
            </div>
        </div>
    </div>

    <div class="flex-1 bg-surface-900 border border-surface-800 rounded-2xl overflow-hidden flex flex-col shadow-2xl">
        
        <div class="p-4 border-b border-surface-800 flex flex-wrap gap-2 items-center bg-surface-900 z-10">
            <span class="text-surface-400 text-xs font-bold mr-2 uppercase"><i class="fa-solid fa-filter mr-1"></i> Filtrar:</span>
            
            <FilterChip label="Todos" :active="filter === 'all'" @click="filter = 'all'" />
            <FilterChip label="Só Dinheiro (Gaveta)" icon="fa-solid fa-money-bill" :active="filter === 'money'" @click="filter = 'money'" />
            <FilterChip label="Cartões / Pix" icon="fa-solid fa-credit-card" :active="filter === 'digital'" @click="filter = 'digital'" />
            <FilterChip label="Entradas" icon="fa-solid fa-arrow-up" color="green" :active="filter === 'in'" @click="filter = 'in'" />
            <FilterChip label="Saídas" icon="fa-solid fa-arrow-down" color="red" :active="filter === 'out'" @click="filter = 'out'" />
        </div>

        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <DataTable :value="filteredTransactions" :rowHover="true" responsiveLayout="scroll"
                class="p-datatable-sm"
                :pt="{
                    headerRow: { class: '!bg-surface-950 !text-surface-400 !text-xs !uppercase !font-bold !tracking-wider' },
                    bodyRow: { class: '!bg-surface-900 hover:!bg-surface-800 !text-surface-200 !transition-colors !border-b !border-surface-800' }
                }"
            >
                <Column field="id" header="ID" class="w-20 font-mono text-xs text-surface-500"></Column>
                
                <Column field="created_at" header="Horário" class="w-32">
                    <template #body="slotProps">
                        <span class="font-mono text-sm">{{ formatTime(slotProps.data.created_at) }}</span>
                    </template>
                </Column>

                <Column field="type" header="Origem / Tipo">
                    <template #body="slotProps">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-sm"
                                 :class="getTypeIconClass(slotProps.data.type)">
                                <i :class="getTypeIcon(slotProps.data.type)"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-sm">{{ translateType(slotProps.data.type) }}</span>
                                <span class="text-[10px] text-surface-400 uppercase">{{ slotProps.data.method }}</span>
                            </div>
                        </div>
                    </template>
                </Column>

                <Column field="description" header="Descrição">
                    <template #body="slotProps">
                        <span v-if="slotProps.data.type === 'sale'" class="text-primary-400 font-bold cursor-pointer hover:underline">
                            Venda #{{ slotProps.data.session_id }}
                        </span>
                        <span v-else>{{ slotProps.data.description || '-' }}</span>
                    </template>
                </Column>

                <Column field="amount" header="Valor" class="text-right">
                    <template #body="slotProps">
                        <span class="font-bold font-mono text-sm" 
                              :class="isNegative(slotProps.data.type) ? 'text-red-400' : 'text-green-400'">
                            {{ isNegative(slotProps.data.type) ? '-' : '+' }} R$ {{ formatMoney(slotProps.data.amount) }}
                        </span>
                    </template>
                </Column>
            </DataTable>

            <div v-if="filteredTransactions.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-500 opacity-50">
                <i class="fa-solid fa-receipt text-4xl mb-3"></i>
                <p>Nenhum lançamento encontrado.</p>
            </div>
        </div>
    </div>

    <CashControlModal v-model:visible="showOperationsModal" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCashStore } from '@/stores/cash-store';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import CashControlModal from '@/components/CashControlModal.vue';

// Componente Local Simples para os Chips
const FilterChip = {
    props: ['label', 'icon', 'active', 'color'],
    template: `
        <button 
            class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-2"
            :class="active 
                ? 'bg-primary-500/20 border-primary-500 text-primary-400' 
                : 'bg-surface-800 border-surface-700 text-surface-400 hover:text-white hover:border-surface-500'"
            @click="$emit('click')"
        >
            <i v-if="icon" :class="icon"></i> {{ label }}
        </button>
    `
};

const store = useCashStore();
const filter = ref('all');
const showOperationsModal = ref(false);

const handleMainAction = () => {
    // Abre o modal. Ele sabe se deve mostrar "Abrir" ou "Fechar" baseado no estado do store
    showOperationsModal.value = true;
};

// Lógica de Filtragem
const filteredTransactions = computed(() => {
    if (!store.transactions) return [];
    
    return store.transactions.filter(t => {
        if (filter.value === 'money') return t.method === 'money';
        if (filter.value === 'digital') return t.method !== 'money';
        if (filter.value === 'in') return !isNegative(t.type);
        if (filter.value === 'out') return isNegative(t.type);
        return true;
    });
});

// Helpers Visuais
const isNegative = (type) => type === 'bleed'; // Sangria é saída. Venda e Suprimento são entradas.

const translateType = (type) => {
    const map = {
        'sale': 'Venda',
        'bleed': 'Sangria',
        'supply': 'Suprimento',
        'opening': 'Abertura' // Se usarmos um tipo específico, senão cai em supply
    };
    return map[type] || type;
};

const getTypeIcon = (type) => {
    if (type === 'sale') return 'fa-solid fa-cart-shopping';
    if (type === 'bleed') return 'fa-solid fa-arrow-right-from-bracket';
    if (type === 'supply' || type === 'opening') return 'fa-solid fa-arrow-right-to-bracket';
    return 'fa-solid fa-circle';
};

const getTypeIconClass = (type) => {
    if (type === 'sale') return 'bg-blue-500/20 text-blue-400';
    if (type === 'bleed') return 'bg-red-500/20 text-red-400';
    return 'bg-green-500/20 text-green-400';
};

const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');
const formatDate = (date) => date ? new Date(date).toLocaleString('pt-BR') : '-';
const formatTime = (date) => date ? new Date(date).toLocaleTimeString('pt-BR') : '-';

onMounted(() => {
    store.checkStatus();
    store.fetchLedger();
});
</script>