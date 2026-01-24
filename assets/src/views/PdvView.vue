<template>
  <div class="w-full h-full relative bg-surface-950">
    
    <div v-if="cashStore.isOpen && sessionStore.sessionType" class="h-full w-full">
        <WaiterView v-if="isMobile" @exit="handleExit" />
        <PosInterface v-else @exit="handleExit" />
    </div>

    <div v-else-if="!cashStore.isOpen" class="flex flex-col items-center justify-center h-full w-full bg-surface-950 relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
        <div class="absolute w-96 h-96 bg-primary-500/10 rounded-full blur-3xl -top-20 -left-20 animate-pulse"></div>
        
        <div class="relative z-10 flex flex-col items-center animate-fade-in-up px-4">            
            <div class="bg-surface-900/80 backdrop-blur-md p-10 rounded-3xl border border-surface-800 shadow-2xl text-center max-w-lg w-full">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-800 border-4 border-surface-700 mb-6 shadow-inner">
                    <i class="fa-solid fa-lock text-3xl text-red-500"></i>
                </div>
                
                <h1 class="text-3xl font-bold text-white mb-2">Caixa Fechado</h1>
                <p class="text-surface-400 text-lg mb-8">É necessário realizar a abertura do caixa para iniciar as operações.</p>

                <Button 
                    label="ABRIR O CAIXA AGORA" 
                    icon="fa-solid fa-key" 
                    class="!w-full !h-16 !text-xl !font-bold !rounded-xl !bg-primary-600 hover:!bg-primary-500 !border-none shadow-lg shadow-primary-500/20 transition-all hover:scale-[1.02]"
                    @click="showOpenModal = true"
                />
            </div>
        </div>
    </div>

    <div v-else class="flex items-center justify-center h-full">
        <i class="fa-solid fa-circle-notch fa-spin text-primary-500 text-3xl"></i>
    </div>

    <CashControlModal v-model:visible="showOpenModal" />

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useCashStore } from '@/stores/cash-store';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import CashControlModal from '@/components/manager/CashControlModal.vue';

// Importação Estática (Resolve problemas de inicialização cíclica)
import PosInterface from '@/components/pdv/PosInterface.vue';
import WaiterView from '@/views/WaiterView.vue';

const sessionStore = useSessionStore();
const cashStore = useCashStore();
const router = useRouter();
const showOpenModal = ref(false);

const windowWidth = ref(window.innerWidth);
const onResize = () => { windowWidth.value = window.innerWidth; };

// Computed simples booleano
const isMobile = computed(() => windowWidth.value < 1024);

const checkRedirect = () => {
    if (cashStore.isOpen && !sessionStore.sessionType) {
        router.replace('/staff/tables');
    }
};

watch(
    () => [cashStore.isOpen, sessionStore.sessionType], 
    () => checkRedirect(),
    { immediate: true }
);

const handleExit = () => {
    sessionStore.leaveSession();
    router.push('/staff/tables');
};

onMounted(async () => {
    window.addEventListener('resize', onResize);
    await cashStore.checkStatus();
    checkRedirect();
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