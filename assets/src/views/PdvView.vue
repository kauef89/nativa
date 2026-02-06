<template>
  <div class="w-full h-full relative overflow-hidden rounded-[24px] bg-surface-1">
    
    <div v-if="cashStore.isOpen && sessionStore.sessionType" class="h-full w-full">
        <WaiterView v-if="isMobile" @exit="handleExit" />
        <PosInterface v-else @exit="handleExit" />
    </div>

    <div v-else-if="!cashStore.isOpen" class="flex flex-col items-center justify-center h-full w-full relative overflow-hidden transition-all">
        
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-[0.03] pointer-events-none"></div>
        <div class="absolute w-96 h-96 bg-primary-500/10 rounded-full blur-3xl -top-20 -left-20 animate-pulse pointer-events-none"></div>
        
        <div class="relative z-10 flex flex-col items-center animate-fade-in-up px-4 max-w-lg w-full">            
            <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-surface-2 mb-8 shadow-2xl">
                <i class="fa-solid fa-lock text-4xl text-red-500"></i>
            </div>
            
            <h1 class="text-3xl font-black text-surface-0 mb-3 tracking-tight">Caixa Fechado</h1>
            <p class="text-surface-400 text-lg mb-10 font-bold text-center leading-relaxed">
                O ponto de venda está inativo.<br>Realize a abertura para começar.
            </p>

            <Button 
                label="ABRIR O CAIXA" 
                icon="fa-solid fa-key" 
                class="!w-full !h-14 !text-lg !bg-primary-600 hover:!bg-primary-500 !text-surface-950 shadow-lg shadow-primary-900/20 transition-all hover:scale-[1.02] !border-none !rounded-full !font-black"
                @click="showOpenModal = true"
            />
        </div>
    </div>

    <div v-else class="flex items-center justify-center h-full">
        <LoadingSpinner size="large" />
    </div>

    <Dialog v-model:visible="showOpenModal" modal header="Abertura de Caixa" :style="{ width: '800px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6 !text-surface-0 !border-b !border-surface-3/10' }, content: { class: '!bg-transparent !p-0' } }">
        
        <div class="h-[500px] flex flex-col">
            <div class="flex-1 overflow-y-auto p-6 scrollbar-thin">
                <p class="text-sm text-surface-400 font-bold mb-4">Informe a contagem inicial do fundo de troco:</p>
                <CashControl v-model="openBreakdown" @change="val => openTotal = val.total" />
            </div>
            
            <div class="p-6 border-t border-surface-3/10 bg-surface-1 flex justify-between items-center gap-4 shrink-0">
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase font-black text-surface-500 tracking-widest">Total Abertura</span>
                    <span class="text-2xl font-black text-green-400">{{ formatCurrency(openTotal) }}</span>
                </div>
                <Button 
                    label="CONFIRMAR E ABRIR" 
                    icon="fa-solid fa-check" 
                    class="!bg-green-600 hover:!bg-green-500 !border-none !font-black !h-12 !text-base !rounded-full shadow-lg shadow-green-900/20 px-8"
                    :loading="loadingAction"
                    @click="handleOpenSubmit"
                />
            </div>
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch, reactive } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useCashStore } from '@/stores/cash-store';
import { useRouter } from 'vue-router';
import { useFormat } from '@/composables/useFormat';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue';
import CashControl from '@/components/manager/CashControl.vue';

import PosInterface from '@/components/pdv/PosInterface.vue';
import WaiterView from '@/views/WaiterView.vue';

const sessionStore = useSessionStore();
const cashStore = useCashStore();
const router = useRouter();
const { formatCurrency } = useFormat();

const showOpenModal = ref(false);
const loadingAction = ref(false);
const openTotal = ref(0);

const openBreakdown = ref({
    bills: [],
    coins: []
});

const windowWidth = ref(window.innerWidth);
const onResize = () => { windowWidth.value = window.innerWidth; };
const isMobile = computed(() => windowWidth.value < 1024);

const checkRedirect = () => {
    // CORREÇÃO: Passamos 'Balcão' como identificador para evitar que o WaiterView
    // rejeite a sessão e cause loop de redirecionamento.
    if (cashStore.isOpen && !sessionStore.sessionType) {
        sessionStore.openSession('counter', 'Balcão', false);
    }
};

watch(
    () => [cashStore.isOpen, sessionStore.sessionType], 
    () => checkRedirect(),
    { immediate: true }
);

const handleOpenSubmit = async () => {
    loadingAction.value = true;
    const success = await cashStore.openRegister(openTotal.value);
    loadingAction.value = false;
    if (success) {
        showOpenModal.value = false;
    }
};

const handleExit = () => {
    sessionStore.leaveSession();
    router.push('/staff/tables');
};

onMounted(async () => {
    window.addEventListener('resize', onResize);
    await cashStore.checkStatus();
    // checkRedirect(); // REMOVIDO: O watcher com immediate: true já cuida disso
});

onUnmounted(() => {
    window.removeEventListener('resize', onResize);
});
</script>

<style scoped>
.animate-fade-in-up { animation: fadeInUp 0.8s ease-out; }
@keyframes fadeInUp { 
    from { opacity: 0; transform: translateY(30px); } 
    to { opacity: 1; transform: translateY(0); } 
}
</style>