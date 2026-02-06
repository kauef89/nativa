<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] overflow-hidden relative transition-all">
    
    <div class="shrink-0 min-h-[70px] px-4 md:px-6 flex items-center justify-between z-20 bg-surface-1 border-b border-surface-3/10 relative">
        
        <div class="flex items-center gap-3 md:gap-4">
            <div class="w-10 h-10 rounded-full bg-surface-2 flex items-center justify-center text-primary-500 shadow-inner border border-surface-3/10">
                <i class="fa-solid fa-fire-burner text-lg"></i>
            </div>
            <div class="flex flex-col justify-center">
                <h1 class="text-lg md:text-xl font-black text-surface-0 leading-none tracking-tight">Produção</h1>
                <p class="text-[10px] font-bold text-surface-400 mt-0.5 hidden md:block">Gerenciamento de Pedidos</p>
            </div>
        </div>

        <div class="flex items-center gap-3 md:gap-6">
            
            <div class="flex items-center gap-2 bg-surface-2 pl-3 pr-1 py-1 rounded-full border border-surface-3/20 shadow-sm transition-all focus-within:ring-2 focus-within:ring-primary-500/20">
                <i class="fa-solid fa-store text-surface-400 text-[10px]"></i>
                <Select 
                    v-model="selectedStationId" 
                    :options="kdsStore.cozinhas" 
                    optionLabel="name" 
                    optionValue="id"
                    class="!bg-transparent !border-none !text-xs !font-black !text-primary-400 !p-0 !w-28 md:!w-32 h-7 flex items-center focus:!shadow-none"
                    :pt="{
                        label: { class: '!p-0 !text-surface-0 !font-bold truncate' },
                        dropdown: { class: '!w-6 !text-surface-400' },
                        overlay: { class: '!bg-surface-2 !border !border-surface-3/20 !shadow-2xl !rounded-xl !mt-2' },
                        option: { class: '!text-surface-300 hover:!bg-surface-3 hover:!text-surface-0 !text-xs !font-bold !py-2.5 !px-3 !rounded-lg !m-1 !transition-colors' },
                        list: { class: '!p-0' }
                    }"
                    placeholder="Selecione..."
                    :loading="kdsStore.isLoading"
                />
            </div>

            <div class="hidden md:block w-px h-6 bg-surface-3/50"></div>
            <div class="hidden md:block text-right">
                <div class="text-xl font-black text-surface-0 leading-none tabular-nums tracking-tight">{{ currentTime }}</div>
            </div>
            
            <Button 
                icon="fa-solid fa-rotate" 
                text rounded 
                class="!w-10 !h-10 !text-surface-400 hover:!text-surface-0 !bg-surface-2/50 hover:!bg-surface-3 transition-all active:scale-90" 
                @click="kdsStore.fetchQueue" 
                v-tooltip.bottom="'Atualizar'"
            />
        </div>
    </div>

    <div class="md:hidden px-4 py-2 bg-surface-1 shrink-0 z-10 flex justify-center">
        <div class="bg-surface-2 p-1.5 rounded-full flex relative shadow-inner border border-surface-3/10 w-fit">
            
            <div 
                class="absolute top-1.5 w-10 h-10 bg-primary-500 rounded-full shadow-lg shadow-primary-500/20 transition-all duration-300 ease-[cubic-bezier(0.2,0,0,1)] z-0"
                :style="mobileTabStyle"
            ></div>

            <button 
                v-for="tab in tabs" :key="tab.id"
                class="w-14 h-10 flex items-center justify-center relative z-10 transition-colors select-none group rounded-full"
                :class="activeTab === tab.id ? 'text-surface-950' : 'text-surface-400 hover:text-surface-200'"
                @click="activeTab = tab.id"
            >
                <div class="relative flex items-center justify-center w-full h-full">
                    <i :class="[tab.icon, activeTab === tab.id ? 'scale-110' : '']" class="text-lg transition-transform duration-300"></i>
                    
                    <transition name="scale">
                        <span v-if="getTabCount(tab.id) > 0" 
                              class="absolute -top-1 -right-1 px-1.5 py-0.5 rounded-full text-[9px] font-black transition-colors min-w-[16px] border-2 flex items-center justify-center leading-none h-4"
                              :class="activeTab === tab.id 
                                ? 'bg-surface-950 text-white border-surface-950' 
                                : 'bg-primary-500 text-surface-950 border-surface-2'">
                            {{ getTabCount(tab.id) }}
                        </span>
                    </transition>
                </div>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-hidden p-4 md:p-6 bg-surface-1 relative">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/carbon-fibre.png')] opacity-[0.03] pointer-events-none mix-blend-overlay"></div>

        <div class="flex h-full w-full gap-4 md:gap-6">
            
            <div 
                class="flex-1 flex-col bg-surface-2/40 rounded-[32px] h-full overflow-hidden relative border border-surface-3/10 transition-all"
                :class="{'hidden md:flex': activeTab !== 'todo', 'flex': activeTab === 'todo'}"
            >
                <div class="p-4 md:p-5 flex justify-between items-center bg-surface-2/30 backdrop-blur-sm sticky top-0 z-10 border-b border-surface-3/5">
                    <span class="font-black text-surface-400 uppercase tracking-[0.2em] text-[10px] flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-surface-500 shadow-[0_0_8px_rgba(120,113,108,0.6)]"></div> 
                        A Fazer
                    </span>
                    <span class="bg-surface-3 text-surface-0 text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-sm">
                        {{ columns.todo.length }}
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-3 pb-20 md:pb-6 scrollbar-thin">
                    <KdsTicket 
                        v-for="(order, index) in columns.todo" 
                        :key="order.id" 
                        :order="order" 
                        column="todo"
                        :isFirst="index === 0"
                        @advance="kdsStore.startOrder"
                        @print-order="kdsStore.reprintTicket"
                        @print-item="kdsStore.printSingleItem"
                        @toggle-item="kdsStore.toggleItemStatus"
                        @request-timer="openTimerConfig"
                    />
                    
                    <div v-if="columns.todo.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 opacity-20">
                        <i class="fa-solid fa-clipboard-check text-4xl mb-2"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest">Fila Vazia</span>
                    </div>
                </div>
            </div>

            <div 
                class="flex-1 flex-col bg-surface-2/60 rounded-[32px] h-full overflow-hidden relative border border-primary-500/20 shadow-lg shadow-primary-900/5 transition-all"
                :class="{'hidden md:flex': activeTab !== 'doing', 'flex': activeTab === 'doing'}"
            >
                <div class="p-4 md:p-5 flex justify-between items-center bg-surface-2/50 backdrop-blur-sm sticky top-0 z-10 border-b border-primary-500/10">
                    <span class="font-black text-primary-400 uppercase tracking-[0.2em] text-[10px] flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-primary-500 animate-pulse shadow-[0_0_10px_rgba(var(--primary-500-rgb),0.6)]"></div> 
                        Preparando
                    </span>
                    <span class="bg-primary-500 text-surface-950 text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-lg shadow-primary-500/20">
                        {{ columns.doing.length }}
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-3 pb-20 md:pb-6 scrollbar-thin">
                    <KdsTicket 
                        v-for="order in columns.doing" 
                        :key="order.id" 
                        :order="order" 
                        column="doing"
                        @advance="kdsStore.readyOrder"
                        @print-order="kdsStore.reprintTicket"
                        @print-item="kdsStore.printSingleItem"
                        @toggle-item="kdsStore.toggleItemStatus"
                        @request-timer="openTimerConfig"
                    />
                     <div v-if="columns.doing.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 opacity-20">
                        <i class="fa-solid fa-fire-burner text-4xl mb-2"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest">Fogão Livre</span>
                    </div>
                </div>
            </div>

            <div 
                class="flex-1 flex-col bg-surface-2/40 rounded-[32px] h-full overflow-hidden relative border border-surface-3/10 transition-all"
                :class="{'hidden md:flex': activeTab !== 'done', 'flex': activeTab === 'done'}"
            >
                <div class="p-4 md:p-5 flex justify-between items-center bg-surface-2/30 backdrop-blur-sm sticky top-0 z-10 border-b border-surface-3/5">
                    <span class="font-black text-green-400 uppercase tracking-[0.2em] text-[10px] flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div> 
                        Prontos
                    </span>
                    <span class="bg-green-500 text-surface-950 text-[10px] font-black px-2.5 py-0.5 rounded-full shadow-lg shadow-green-500/20">
                        {{ columns.done.length }}
                    </span>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-3 pb-20 md:pb-6 scrollbar-thin">
                    <KdsTicket 
                        v-for="order in columns.done" 
                        :key="order.id" 
                        :order="order" 
                        column="done"
                        @finish="kdsStore.finishOrder"
                        @print-order="kdsStore.reprintTicket"
                        @print-item="kdsStore.printSingleItem"
                        @toggle-item="kdsStore.toggleItemStatus"
                    />
                     <div v-if="columns.done.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 opacity-20">
                        <i class="fa-solid fa-check-double text-4xl mb-2"></i>
                        <span class="text-[10px] font-black uppercase tracking-widest">Sem Entregas</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <Dialog v-model:visible="configModal.visible" modal header="Definir Tempo" 
            :style="{ width: '350px', maxWidth: '90vw' }" 
            :pt="{ 
                root: { class: '!bg-surface-2 !border-none !rounded-[28px] !shadow-2xl' }, 
                header: { class: '!bg-transparent !p-6 !pb-2 !text-surface-0 !border-none' }, 
                content: { class: '!p-6 !bg-transparent' },
                mask: { class: '!bg-surface-base/80 backdrop-blur-sm' }
            }">
        <div class="flex flex-col gap-6 text-center">
            <div>
                <p class="text-[10px] text-surface-400 font-black uppercase tracking-widest mb-2">Item Selecionado</p>
                <p class="text-lg font-black text-surface-0 leading-tight">{{ configModal.itemName }}</p>
            </div>
            
            <div class="grid grid-cols-3 gap-3">
                <button v-for="t in [5, 10, 15, 20, 30, 45]" :key="t" 
                        @click="confirmTimer(t)" 
                        class="bg-surface-1 hover:bg-primary-500 hover:text-surface-950 text-surface-200 py-3 rounded-2xl font-black transition-all border border-surface-3/20 hover:border-transparent active:scale-95 shadow-sm">
                    {{ t }} min
                </button>
            </div>

            <div v-if="showCustomInput" class="animate-fade-in">
                <div class="flex gap-2">
                    <input type="number" v-model="customMinutes" class="flex-1 bg-surface-1 text-surface-0 rounded-xl px-4 font-bold text-center border-none focus:ring-2 focus:ring-primary-500 h-12 outline-none" placeholder="00" @keydown.enter="confirmTimer(customMinutes)">
                    <button class="bg-primary-500 text-surface-950 font-black px-6 rounded-xl hover:bg-primary-400 transition-colors" @click="confirmTimer(customMinutes)">OK</button>
                </div>
            </div>
            
            <button v-else @click="showCustomInput = true" class="text-xs font-bold text-surface-400 hover:text-surface-0 underline decoration-dashed underline-offset-4 transition-colors">
                Definir outro tempo...
            </button>
        </div>
    </Dialog>

    <Dialog v-model:visible="isAlarmVisible" modal :closable="false" :draggable="false" :dismissableMask="false"
            :pt="{ 
                root: { class: '!bg-red-600 !border-none !rounded-[32px] !shadow-[0_0_100px_rgba(220,38,38,0.8)] animate-pulse m-4' }, 
                content: { class: '!p-8 text-center flex flex-col items-center !bg-transparent' },
                mask: { class: '!bg-red-950/95 backdrop-blur-xl' }
            }">
        <div class="w-24 h-24 rounded-full bg-white text-red-600 flex items-center justify-center mb-6 animate-bounce shadow-2xl">
            <i class="fa-solid fa-bell text-5xl"></i>
        </div>
        
        <h2 class="text-3xl font-black text-white uppercase tracking-tight mb-2">Tempo Esgotado!</h2>
        
        <div class="bg-black/20 rounded-2xl p-4 mb-8 w-full backdrop-blur-sm border border-white/10">
            <p class="text-white/80 text-xs font-bold uppercase tracking-widest mb-1">{{ kdsStore.activeAlarm?.orderLabel }}</p>
            <p class="text-xl font-black text-white leading-tight">{{ kdsStore.activeAlarm?.itemName }}</p>
        </div>

        <div class="flex flex-col gap-3 w-full">
            <Button label="PARAR ALARME" class="!bg-white hover:!bg-gray-100 !text-red-600 !font-black !text-lg !rounded-full !h-14 shadow-2xl transition-transform hover:!scale-[1.02] w-full !border-none" @click="stopAlarm" />
            <Button label="Reiniciar Timer (+5min)" class="!bg-red-800 hover:!bg-red-900 !text-white !font-bold !text-sm !rounded-full !h-12 w-full !border border-white/20" @click="restartTimerFlow" />
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, reactive } from 'vue';
import { useKdsStore } from '@/stores/kds-store';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import KdsTicket from '@/components/kds/KdsTicket.vue';

const kdsStore = useKdsStore();
const now = ref(new Date());
let clockInterval;

// COMPUETD REATIVA PARA SINCRONIZAR DROPDOWN E STORE
const selectedStationId = computed({
    get: () => kdsStore.selectedCozinhaId,
    set: (val) => kdsStore.setCozinha(val)
});

// Mobile Tabs State
const activeTab = ref('todo');
const tabs = [
    { id: 'todo', label: 'A Fazer', icon: 'fa-solid fa-clipboard-list' },
    { id: 'doing', label: 'Preparando', icon: 'fa-solid fa-fire-burner' },
    { id: 'done', label: 'Prontos', icon: 'fa-solid fa-check-double' }
];

const mobileTabStyle = computed(() => {
    const index = tabs.findIndex(t => t.id === activeTab.value);
    const itemWidth = 56; // w-14
    return {
        transform: `translateX(${index * itemWidth + 8}px)` // 8px de offset
    };
});

// Timer & Alarm Logic
const configModal = reactive({ visible: false, order: null, item: null, itemName: '' });
const showCustomInput = ref(false);
const customMinutes = ref('');

const isAlarmVisible = computed(() => !!kdsStore.activeAlarm);
const currentTime = computed(() => now.value.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }));
const columns = computed(() => kdsStore.columns);

const getTabCount = (tabId) => {
    if(tabId === 'todo') return columns.value.todo.length;
    if(tabId === 'doing') return columns.value.doing.length;
    if(tabId === 'done') return columns.value.done.length;
    return 0;
};

const openTimerConfig = (order, item) => {
    configModal.order = order;
    configModal.item = item;
    configModal.itemName = item.name;
    showCustomInput.value = false;
    customMinutes.value = '';
    configModal.visible = true;
};

const confirmTimer = (minutes) => {
    const val = parseInt(minutes);
    if (!val || val <= 0) return;
    kdsStore.startTimer(configModal.order, configModal.item, val);
    configModal.visible = false;
};

const stopAlarm = () => {
    kdsStore.silenceAlarm();
};

const restartTimerFlow = () => {
    const data = kdsStore.activeAlarm;
    stopAlarm();
    const order = kdsStore.orders.find(o => o.id === data.orderId);
    if (order) {
        const item = order.items.find(i => i.id === data.itemId);
        if (item) kdsStore.startTimer(order, item, 5);
    }
};

onMounted(async () => {
    await kdsStore.fetchCozinhas();
    // A lógica de seleção automática agora está dentro do fetchCozinhas na store
    kdsStore.startPolling();
    clockInterval = setInterval(() => { now.value = new Date(); }, 1000);
});

onUnmounted(() => {
    kdsStore.stopPolling();
    if (clockInterval) clearInterval(clockInterval);
});
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar { width: 4px; }
.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 99px;
}
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }

.animate-fade-in { animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.scale-enter-active, .scale-leave-active { transition: all 0.2s ease; }
.scale-enter-from, .scale-leave-to { transform: scale(0); opacity: 0; }
</style>