<template>
  <Dialog 
    v-model:visible="visible" 
    modal 
    :style="{ width: '900px' }"
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-800' },
        header: { class: '!bg-surface-950 !border-b !border-surface-800 !text-white !py-4 !px-6' },
        content: { class: '!bg-surface-900 !p-0' },
        closeButton: { class: '!text-surface-400 hover:!text-white' }
    }"
    @show="onShow"
  >
    <template #header>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-surface-800 flex items-center justify-center border border-surface-700 text-primary-500">
                <i class="fa-solid fa-cash-register text-lg"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">
                    {{ store.isOpen ? 'Controle de Caixa' : 'Abertura de Caixa' }}
                </h2>
                <div v-if="!store.isOpen" class="flex items-center gap-2 mt-1">
                    <span class="text-xs text-surface-400" :class="{'text-white font-bold': openingStep === 1}">1. Cédulas</span>
                    <i class="fa-solid fa-chevron-right text-[10px] text-surface-600"></i>
                    <span class="text-xs text-surface-400" :class="{'text-white font-bold': openingStep >= 2}">2. Moedas</span>
                </div>
                <p v-else class="text-xs text-green-400 font-bold flex items-center gap-2 mt-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Aberto
                </p>
            </div>
        </div>
    </template>

    <div v-if="!store.isOpen" class="flex flex-col h-[600px] bg-surface-900 relative">
        
        <div class="flex-1 overflow-y-auto scrollbar-thin pt-6 px-6">
            
            <div v-if="openingStep === 1" class="animate-fade-in">
                <div class="bg-surface-950 p-5 rounded-2xl border border-surface-800 mb-6">
                    <div class="text-xs font-bold text-surface-400 uppercase mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-money-bill text-green-500"></i> Cédulas (Aporte do Cofre)
                    </div>
                    <div class="grid grid-cols-4 md:grid-cols-7 gap-3">
                        <div v-for="denom in bills" :key="denom.val" 
                             class="flex flex-col items-center bg-surface-900 border border-surface-700 p-3 rounded-xl shadow-sm transition-all duration-200 group focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 focus-within:bg-surface-800 focus-within:-translate-y-1">
                            <span class="text-lg font-black text-white mb-2 font-mono tracking-tighter whitespace-nowrap">{{ formatLabel(denom.val) }}</span>
                            <InputNumber 
                                v-model="denom.qty" 
                                hideButtons buttonLayout="stacked" 
                                :min="0" :step="1" 
                                inputClass="nativa-cash-input !w-full !text-center !p-1 !text-lg !font-bold !bg-transparent !text-white !border-none !shadow-none focus:!ring-0" 
                                class="w-full" 
                                decrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" 
                                incrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" 
                                @keydown.right.prevent="focusNext" 
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-surface-800 p-5 rounded-2xl flex justify-between items-center border border-surface-700 shadow-lg">
                    <div class="flex flex-col">
                        <span class="text-surface-400 font-bold uppercase text-xs tracking-wider">Subtotal Cédulas</span>
                        <span class="text-xs text-surface-500">Valor em papel moeda</span>
                    </div>
                    <span class="text-3xl font-mono font-bold text-white tracking-tight">R$ {{ formatMoney(billsTotal) }}</span>
                </div>
            </div>

            <div v-else-if="openingStep === 2 && savedCoinsAvailable && !manualCoinsStarted" class="flex flex-col items-center justify-center h-full animate-fade-in">
                
                <div class="w-20 h-20 bg-yellow-500/10 rounded-full flex items-center justify-center mb-6 animate-bounce">
                    <i class="fa-solid fa-piggy-bank text-4xl text-yellow-500"></i>
                </div>

                <h3 class="text-2xl font-bold text-white mb-2">Moedas na Gaveta</h3>
                <p class="text-surface-400 text-center max-w-md mb-8">
                    Identificamos um saldo de <strong class="text-white">R$ {{ formatMoney(savedCoinsTotal) }}</strong> em moedas no último fechamento. Deseja manter este valor ou realizar uma nova contagem?
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full max-w-lg">
                    <button 
                        class="p-6 rounded-2xl border border-surface-700 bg-surface-950 hover:bg-surface-800 hover:border-surface-600 transition-all group flex flex-col items-center gap-3"
                        @click="manualCoinsStarted = true"
                    >
                        <i class="fa-solid fa-hand-holding-dollar text-2xl text-surface-400 group-hover:text-white"></i>
                        <span class="font-bold text-surface-300 group-hover:text-white">Informar Novo Valor</span>
                    </button>

                    <button 
                        class="p-6 rounded-2xl border border-primary-500/30 bg-primary-500/10 hover:bg-primary-500/20 transition-all group flex flex-col items-center gap-3 relative overflow-hidden"
                        @click="useSavedCoinsAndOpen"
                    >
                        <div class="absolute inset-0 bg-primary-500/5 blur-xl group-hover:bg-primary-500/10 transition-all"></div>
                        <i class="fa-solid fa-check-circle text-2xl text-primary-500"></i>
                        <span class="font-bold text-primary-400">Usar R$ {{ formatMoney(savedCoinsTotal) }} e Abrir</span>
                    </button>
                </div>
            </div>

            <div v-else class="animate-fade-in">
                <div class="bg-surface-950 p-5 rounded-2xl border border-surface-800 mb-6">
                    <div class="text-xs font-bold text-surface-400 uppercase mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-coins text-yellow-500"></i> Moedas (Gaveta)
                    </div>
                    <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                        <div v-for="denom in coins" :key="denom.val" 
                             class="flex flex-col items-center bg-surface-900 border border-surface-700 p-3 rounded-xl shadow-sm transition-all duration-200 group focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 focus-within:bg-surface-800 focus-within:-translate-y-1">
                            <span class="text-lg font-bold text-white mb-2 font-mono tracking-tighter whitespace-nowrap">{{ formatLabel(denom.val) }}</span>
                            <InputNumber 
                                v-model="denom.qty" 
                                Buttons buttonLayout="stacked" 
                                :min="0" :step="1" 
                                inputClass="nativa-cash-input !w-full !text-center !p-1 !text-lg !font-bold !bg-transparent !text-white !border-none !shadow-none focus:!ring-0" 
                                class="w-full" 
                                decrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" 
                                incrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" 
                                @keydown.right.prevent="focusNext" 
                            />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-surface-950 p-4 rounded-xl border border-surface-800">
                        <span class="text-surface-500 text-xs font-bold uppercase block mb-1">Cédulas</span>
                        <span class="text-lg font-mono font-bold text-white">R$ {{ formatMoney(billsTotal) }}</span>
                    </div>
                    <div class="bg-surface-800 p-4 rounded-xl border border-surface-700 shadow-lg">
                        <span class="text-surface-400 text-xs font-bold uppercase block mb-1">Total Fundo de Troco</span>
                        <span class="text-2xl font-mono font-bold text-green-400">R$ {{ formatMoney(calculatedTotal) }}</span>
                    </div>
                </div>
            </div>

        </div>

        <div class="p-6 border-t border-surface-800 bg-surface-950 flex gap-3">
            <Button 
                v-if="openingStep === 2 || (openingStep === 2 && manualCoinsStarted)"
                label="Voltar" 
                icon="fa-solid fa-arrow-left" 
                class="!bg-surface-800 hover:!bg-surface-700 !border-surface-700 !text-surface-300 !font-bold !w-32"
                @click="goBackStep"
            />
            
            <Button 
                v-if="openingStep === 1"
                label="CONTINUAR" 
                icon="fa-solid fa-arrow-right" 
                iconPos="right"
                class="!bg-primary-600 hover:!bg-primary-500 !border-none !flex-1 !font-bold !h-12 !text-lg !rounded-xl"
                @click="goToCoinsStep"
            />

            <Button 
                v-if="openingStep === 2 && (!savedCoinsAvailable || manualCoinsStarted)"
                label="CONFIRMAR E ABRIR CAIXA" 
                icon="fa-solid fa-check" 
                class="!bg-green-600 hover:!bg-green-500 !border-none !flex-1 !font-bold !h-12 !text-lg !rounded-xl shadow-lg shadow-green-900/20"
                @click="handleOpen"
            />
        </div>
    </div>

    <div v-else class="flex flex-col h-[700px]">
        <div class="bg-surface-950 p-5 border-b border-surface-800 grid grid-cols-2 gap-4 shrink-0">
            <div class="bg-surface-900 p-4 rounded-2xl border border-surface-800 flex flex-col justify-center">
                <div class="text-surface-400 text-xs uppercase font-bold mb-1 tracking-wider">Saldo em Gaveta</div>
                <div class="text-3xl font-mono font-bold text-green-400">R$ {{ formatMoney(store.summary.cash_balance) }}</div>
            </div>
            <div class="bg-surface-900 p-4 rounded-2xl border border-surface-800 flex flex-col justify-center">
                <div class="text-surface-400 text-xs uppercase font-bold mb-1 tracking-wider">Faturamento Total</div>
                <div class="text-3xl font-mono font-bold text-white">R$ {{ formatMoney(store.summary.total_in) }}</div>
            </div>
        </div>

        <div class="flex-1 overflow-hidden flex flex-col">
            <TabView v-model:activeIndex="activeTab" :pt="{ nav: { class: '!bg-surface-950 !border-b !border-surface-800 !px-4' }, inkbar: { class: '!bg-primary-500 !h-1' }, panelContainer: { class: '!bg-surface-900 !p-0 !h-full !overflow-y-auto' } }">
                
                <TabPanel header="Sangria / Suprimento">
                    <div class="p-8 flex flex-col gap-8 max-w-2xl mx-auto">
                        <div class="grid grid-cols-2 gap-6">
                            <div class="cursor-pointer border-2 border-surface-700 rounded-2xl p-6 text-center hover:border-red-500 hover:bg-surface-800 transition-all group" :class="{'!border-red-500 !bg-red-500/10': moveType === 'bleed'}" @click="moveType = 'bleed'">
                                <div class="w-14 h-14 rounded-full bg-surface-950 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fa-solid fa-arrow-down text-red-500 text-2xl"></i></div>
                                <div class="font-bold text-lg text-white mb-1">Sangria</div>
                                <div class="text-xs text-surface-400">Retirada de valor</div>
                            </div>
                            <div class="cursor-pointer border-2 border-surface-700 rounded-2xl p-6 text-center hover:border-green-500 hover:bg-surface-800 transition-all group" :class="{'!border-green-500 !bg-green-500/10': moveType === 'supply'}" @click="moveType = 'supply'">
                                <div class="w-14 h-14 rounded-full bg-surface-950 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fa-solid fa-arrow-up text-green-500 text-2xl"></i></div>
                                <div class="font-bold text-lg text-white mb-1">Suprimento</div>
                                <div class="text-xs text-surface-400">Entrada de troco</div>
                            </div>
                        </div>
                        <div class="space-y-4 bg-surface-950 p-6 rounded-2xl border border-surface-800">
                            <div class="flex flex-col gap-2"><label class="text-xs font-bold text-surface-400 uppercase">Valor da Operação</label><InputNumber v-model="amountInput" mode="currency" currency="BRL" locale="pt-BR" class="w-full" :inputClass="'!bg-surface-900 !border-surface-700 !text-white !h-14 !text-2xl !font-bold !text-center focus:!border-primary-500'" placeholder="R$ 0,00"/></div>
                            <div class="flex flex-col gap-2"><label class="text-xs font-bold text-surface-400 uppercase">Motivo</label><InputText v-model="descInput" placeholder="Ex: Pagamento Fornecedor de Gelo" class="!bg-surface-900 !border-surface-700 !text-white !h-12"/></div>
                            <Button :label="moveType === 'bleed' ? 'CONFIRMAR RETIRADA' : 'CONFIRMAR ENTRADA'" :icon="moveType === 'bleed' ? 'fa-solid fa-minus' : 'fa-solid fa-plus'" class="w-full !mt-2 font-bold !h-14 !text-lg !rounded-xl" :class="moveType === 'bleed' ? '!bg-red-600 hover:!bg-red-500 !border-none' : '!bg-green-600 hover:!bg-green-500 !border-none'" @click="handleMove"/>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel header="Extrato Rápido">
                    <div class="p-0">
                        <DataTable :value="store.transactions" scrollable scrollHeight="450px" class="text-sm" :pt="{ headerRow: { class: '!bg-surface-950 !text-surface-400' }, bodyRow: { class: '!bg-surface-900 !text-surface-200 !h-12' } }">
                            <Column field="created_at" header="Hora" class="font-mono text-surface-400"><template #body="slotProps">{{ formatTime(slotProps.data.created_at) }}</template></Column>
                            <Column field="description" header="Descrição"><template #body="slotProps"><div class="flex flex-col"><span class="font-bold">{{ slotProps.data.type === 'sale' ? 'Venda #' + (slotProps.data.session_id || '-') : slotProps.data.description }}</span><span class="text-[10px] uppercase text-surface-500 tracking-wider">{{ translateType(slotProps.data.type) }}</span></div></template></Column>
                            <Column field="amount" header="Valor" class="text-right"><template #body="slotProps"><span :class="getClass(slotProps.data.type)">{{ slotProps.data.type === 'bleed' ? '-' : '+' }} R$ {{ formatMoney(slotProps.data.amount) }}</span></template></Column>
                        </DataTable>
                    </div>
                </TabPanel>

                <TabPanel header="Conferência e Fechamento">
                    <div class="flex flex-col h-full">
                        <div class="p-6 bg-surface-950 border-b border-surface-800">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded bg-primary-500/20 text-primary-500 flex items-center justify-center font-bold text-sm">1</div>
                                <span class="text-white font-bold">Conferência Física (Cédulas + Moedas)</span>
                            </div>
                            <p class="text-surface-400 text-sm ml-11">Conte o dinheiro da gaveta e preencha abaixo.</p>
                        </div>

                        <div class="flex-1 overflow-y-auto scrollbar-thin pt-6 px-6">
                            
                            <div class="flex flex-col gap-6 mb-6 pt-4">
                                <div class="bg-surface-950 p-5 rounded-2xl border border-surface-800">
                                    <div class="text-xs font-bold text-surface-400 uppercase mb-4 flex items-center gap-2">
                                        <i class="fa-solid fa-money-bill text-green-500"></i> Cédulas
                                    </div>
                                    <div class="grid grid-cols-4 md:grid-cols-7 gap-3">
                                        <div v-for="denom in bills" :key="denom.val" 
                                            class="flex flex-col items-center bg-surface-900 border border-surface-700 p-3 rounded-xl shadow-sm transition-all duration-200 group focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 focus-within:bg-surface-800 focus-within:-translate-y-1">
                                            <span class="text-lg font-bold text-white mb-2 font-mono tracking-tighter">{{ formatLabel(denom.val) }}</span>
                                            <InputNumber v-model="denom.qty" hideButtons buttonLayout="stacked" :min="0" :step="1" inputClass="nativa-cash-input !w-full !text-center !p-1 !text-lg !font-bold !bg-transparent !text-white !border-none !shadow-none focus:!ring-0" class="w-full" decrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" incrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" @keydown.right.prevent="focusNext" />
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-surface-950 p-5 rounded-2xl border border-surface-800">
                                    <div class="text-xs font-bold text-surface-400 uppercase mb-4 flex items-center gap-2">
                                        <i class="fa-solid fa-coins text-yellow-500"></i> Moedas
                                    </div>
                                    <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                                        <div v-for="denom in coins" :key="denom.val" 
                                            class="flex flex-col items-center bg-surface-900 border border-surface-700 p-3 rounded-xl shadow-sm transition-all duration-200 group focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500 focus-within:bg-surface-800 focus-within:-translate-y-1">
                                            <span class="text-lg font-bold text-white mb-2 font-mono tracking-tighter">{{ formatLabel(denom.val) }}</span>
                                            <InputNumber v-model="denom.qty" hideButtons buttonLayout="stacked" :min="0" :step="1" inputClass="nativa-cash-input !w-full !text-center !p-1 !text-lg !font-bold !bg-transparent !text-white !border-none !shadow-none focus:!ring-0" class="w-full" decrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" incrementButtonClass="!bg-surface-800 !text-surface-400 hover:!bg-surface-700 !h-6 !border-none" @keydown.right.prevent="focusNext" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6 bg-surface-800 p-5 rounded-2xl flex justify-between items-center border border-surface-700 shadow-lg">
                                <div class="flex flex-col">
                                    <span class="text-surface-400 font-bold uppercase text-xs tracking-wider">Total Conferido</span>
                                    <span class="text-xs text-surface-500">Valor físico na gaveta</span>
                                </div>
                                <span class="text-4xl font-mono font-bold text-green-400 tracking-tight">R$ {{ formatMoney(calculatedTotal) }}</span>
                            </div>

                        </div>

                        <div class="p-6 bg-surface-950 border-t border-surface-800 shrink-0">
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div class="bg-surface-900 p-3 rounded-xl border border-surface-800">
                                    <span class="text-surface-400 text-xs font-bold uppercase block mb-1">Esperado</span>
                                    <span class="text-xl font-bold text-surface-200">R$ {{ formatMoney(store.summary.cash_balance) }}</span>
                                </div>
                                <div class="bg-surface-800 p-3 rounded-xl border border-surface-700 shadow-inner">
                                    <span class="text-surface-400 text-xs font-bold uppercase block mb-1">Conferido</span>
                                    <span class="text-xl font-bold text-primary-400">R$ {{ formatMoney(calculatedTotal) }}</span>
                                </div>
                            </div>

                            <div v-if="diff !== 0" class="p-3 rounded-xl border text-center text-sm font-bold mb-4 flex items-center justify-center gap-2" 
                                :class="diff > 0 ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-400 border-red-500/20'">
                                <i class="fa-solid" :class="diff > 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                                Diferença: {{ diff > 0 ? 'Sobrando' : 'Faltando' }} R$ {{ formatMoney(Math.abs(diff)) }}
                            </div>

                            <div class="flex flex-col gap-2 mb-4">
                                <label class="text-xs font-bold text-surface-400 uppercase">Observações Finais</label>
                                <Textarea v-model="notesInput" rows="2" class="!bg-surface-900 !border-surface-700 !text-white w-full text-sm" placeholder="Justifique eventuais diferenças..." />
                            </div>

                            <Button 
                                label="ENCERRAR CAIXA E SAIR" 
                                icon="fa-solid fa-lock" 
                                class="!bg-red-600 hover:!bg-red-500 !border-none !w-full !font-bold !h-14 !text-lg !rounded-xl shadow-lg shadow-red-900/20"
                                @click="handleClose"
                            />
                        </div>
                    </div>
                </TabPanel>
            </TabView>
        </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router'; 
import { useCashStore } from '@/stores/cash-store';
import { useSessionStore } from '@/stores/session-store'; 
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const props = defineProps(['visible']);
const emit = defineEmits(['update:visible']);

const router = useRouter();
const store = useCashStore();
const sessionStore = useSessionStore();
const activeTab = ref(0);

// Wizard de Abertura
const openingStep = ref(1);

// Estado das Denominações
const bills = ref([
    { val: 200, qty: 0 }, { val: 100, qty: 0 }, { val: 50, qty: 0 }, 
    { val: 20, qty: 0 }, { val: 10, qty: 0 }, { val: 5, qty: 0 }, { val: 2, qty: 0 }
]);
const coins = ref([
    { val: 1, qty: 0 }, { val: 0.50, qty: 0 }, { val: 0.25, qty: 0 }, 
    { val: 0.10, qty: 0 }, { val: 0.05, qty: 0 }
]);

// Lógica de Memória de Moedas
const savedCoinsAvailable = ref(false);
const savedCoinsTotal = ref(0);
const usingSavedCoins = ref(false);
const manualCoinsStarted = ref(false);

const amountInput = ref(0);
const descInput = ref('');
const moveType = ref('bleed');
const notesInput = ref('');

const visible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

// Totais parciais e geral
const billsTotal = computed(() => {
    let total = 0;
    bills.value.forEach(b => total += b.val * b.qty);
    return total;
});

const calculatedTotal = computed(() => {
    let total = billsTotal.value;
    // Se estiver usando moedas salvas e ainda não foi para manual, soma elas
    if (savedCoinsAvailable.value && usingSavedCoins.value && !manualCoinsStarted.value) {
        total += savedCoinsTotal.value;
    } else {
        coins.value.forEach(c => total += c.val * c.qty);
    }
    return total;
});

const diff = computed(() => {
    return calculatedTotal.value - (store.summary.cash_balance || 0);
});

// --- NOVO: Check via STORE e não LocalStorage ---
const checkSavedCoins = () => {
    if (store.lastClosing && store.lastClosing.coins) {
        try {
            const savedList = store.lastClosing.coins;
            let total = 0;
            if(Array.isArray(savedList)) {
                savedList.forEach(c => total += c.val * c.qty);
                if (total > 0) {
                    savedCoinsAvailable.value = true;
                    savedCoinsTotal.value = total;
                }
            }
        } catch(e) { console.error('Erro ao ler moedas do DB', e); }
    }
};

const useSavedCoinsAndOpen = async () => {
    // Carrega o valor salvo no array local (para envio correto na API)
    if (store.lastClosing && store.lastClosing.coins) {
        coins.value = JSON.parse(JSON.stringify(store.lastClosing.coins));
    }
    usingSavedCoins.value = true;
    await handleOpen();
};

const onShow = async () => {
    await store.checkStatus(); 
    store.fetchLedger();
    
    // Reset wizard
    openingStep.value = 1;
    
    // Reset contagem
    bills.value.forEach(b => b.qty = 0);
    coins.value.forEach(c => c.qty = 0);
    
    // Reseta flags
    savedCoinsAvailable.value = false;
    usingSavedCoins.value = false;
    manualCoinsStarted.value = false;
    
    if (!store.isOpen) {
        checkSavedCoins();
    }
    
    amountInput.value = 0;
    descInput.value = '';
    moveType.value = 'bleed';
    notesInput.value = '';
};

// Wizard Navigation
const goToCoinsStep = () => {
    // Se houver moedas salvas, o fluxo vai para a tela de decisão (Step 2)
    // Se não, vai direto para a tela de inputs manuais (Step 3 lógica do template)
    if (savedCoinsAvailable.value) {
        openingStep.value = 2;
    } else {
        // Pula a decisão e vai para contagem manual
        manualCoinsStarted.value = true; 
        openingStep.value = 2; // Na verdade usa o v-else do template que checa manualCoinsStarted
    }
};

const goBackStep = () => {
    if (openingStep.value === 2) {
        if (savedCoinsAvailable.value && manualCoinsStarted.value) {
            // Se estava na contagem manual mas tinha opção de saldo, volta para a decisão
            manualCoinsStarted.value = false;
        } else {
            // Volta para cédulas
            openingStep.value = 1;
        }
    }
};

// --- NAVEGAÇÃO "TAB" COM SETA DIREITA ---
const focusNext = (event) => {
    const inputs = document.querySelectorAll('.nativa-cash-input');
    const currentInput = event.target;
    const inputsArray = Array.from(inputs);
    const currentIndex = inputsArray.indexOf(currentInput);

    if (currentIndex >= 0 && currentIndex < inputsArray.length - 1) {
        inputsArray[currentIndex + 1].focus();
        inputsArray[currentIndex + 1].select();
    }
};

// Actions
// Ação de Abertura de Caixa
const handleOpen = async () => {
    // 1. Tenta abrir o caixa na API com o valor calculado
    const success = await store.openRegister(calculatedTotal.value);
    
    if (success) {
        // 2. Fecha o modal imediatamente (para não exibir a tela de operações)
        // Isso dispara o emit('update:visible', false) através do computed 'visible'
        visible.value = false;
        
        // 3. Redireciona para o Mapa de Mesas (Fluxo correto de início de dia)
        router.push('/tables');
    }
};

const handleMove = async () => {
    if (amountInput.value <= 0) return;
    const success = await store.addMovement(moveType.value, amountInput.value, descInput.value);
    if (success) {
        amountInput.value = 0;
        descInput.value = '';
        activeTab.value = 1; 
    }
};

const handleClose = async () => {
    if (!confirm('Confirma o fechamento do caixa?')) return;
    
    const breakdown = {
        bills: bills.value,
        coins: coins.value
    };
    
    const success = await store.closeRegister(calculatedTotal.value, notesInput.value, breakdown);
    
    if (success) {
        visible.value = false;
        sessionStore.leaveSession(); 
        router.push('/pdv'); 
    }
};

// Helpers
const formatMoney = (val) => parseFloat(val).toFixed(2).replace('.', ',');
const formatLabel = (val) => 'R$ ' + parseFloat(val).toFixed(2).replace('.', ',');
const formatTime = (iso) => iso ? new Date(iso).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'}) : '-';
const translateType = (t) => ({ sale: 'Venda', bleed: 'Sangria', supply: 'Suprimento', opening: 'Abertura' }[t] || t);
const getClass = (t) => t === 'bleed' ? 'text-red-400 font-bold' : (t === 'supply' || t === 'opening' ? 'text-green-400 font-bold' : 'text-white');
const resetCoinsSelection = () => { manualCoinsStarted.value = false; usingSavedCoins.value = false; };
</script>