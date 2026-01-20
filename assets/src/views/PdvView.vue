<template>
  <div class="w-full h-full relative bg-surface-950">
    
    <div v-if="cashStore.isOpen && sessionStore.sessionType" class="h-full w-full">
        <pos-interface @exit="handleExit" />
    </div>

    <div v-else-if="cashStore.isOpen" class="flex flex-col items-center justify-center h-full text-surface-500">
        <div class="bg-surface-900 p-8 rounded-3xl border border-surface-800 shadow-2xl text-center max-w-md">
            <div class="w-20 h-20 bg-primary-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-cash-register text-4xl text-primary-500"></i>
            </div>
            
            <h2 class="text-2xl font-bold text-white mb-2">Caixa Aberto</h2>
            <p class="text-surface-400 mb-8">O PDV está pronto. Como deseja iniciar a venda?</p>
            
            <div class="flex flex-col gap-3">
                <Button label="Mapa de Mesas" icon="fa-solid fa-map" class="!w-full !py-3" @click="$router.push('/tables')" />
                <Button label="Balcão / Rápido" icon="fa-solid fa-bag-shopping" severity="secondary" class="!w-full !py-3 !bg-surface-800 !text-white !border-surface-700 hover:!bg-surface-700" @click="$router.push('/counter')" />
            </div>
        </div>
    </div>

    <div v-else class="flex flex-col items-center justify-center h-full w-full bg-surface-950 relative overflow-hidden">
        
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
        <div class="absolute w-96 h-96 bg-primary-500/10 rounded-full blur-3xl -top-20 -left-20 animate-pulse"></div>
        <div class="absolute w-96 h-96 bg-purple-500/10 rounded-full blur-3xl -bottom-20 -right-20 animate-pulse" style="animation-delay: 1s"></div>

        <div class="relative z-10 flex flex-col items-center animate-fade-in-up">            
            <div class="bg-surface-900/80 backdrop-blur-md p-10 rounded-3xl border border-surface-800 shadow-2xl text-center max-w-lg w-full mx-4">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-800 border-4 border-surface-700 mb-6 shadow-inner">
                    <i class="fa-solid fa-lock text-3xl text-red-500"></i>
                </div>
                
                <h1 class="text-3xl font-bold text-white mb-2">Caixa Fechado</h1>
                <p class="text-surface-400 text-lg mb-8">É necessário realizar a abertura do caixa para iniciar as operações de venda.</p>

                <Button 
                    label="ABRIR O CAIXA AGORA" 
                    icon="fa-solid fa-key" 
                    class="!w-full !h-16 !text-xl !font-bold !rounded-xl !bg-primary-600 hover:!bg-primary-500 !border-none shadow-lg shadow-primary-500/20 transition-all hover:scale-[1.02]"
                    @click="showOpenModal = true"
                />
                
                <div class="mt-6 pt-6 border-t border-surface-800 text-xs text-surface-500">
                    <i class="fa-solid fa-clock mr-1"></i> Sistema pronto. Aguardando operador.
                </div>
            </div>
        </div>
    </div>

    <CashControlModal v-model:visible="showOpenModal" />

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useCashStore } from '@/stores/cash-store'; // <--- Importar Store de Caixa
import { useRouter } from 'vue-router';
import PosInterface from '@/components/PosInterface.vue';
import Button from 'primevue/button';
import CashControlModal from '@/components/CashControlModal.vue';

const sessionStore = useSessionStore();
const cashStore = useCashStore(); // <--- Usar Store
const router = useRouter();
const showOpenModal = ref(false);

const handleExit = () => {
    sessionStore.leaveSession();
    if (sessionStore.sessionType === 'table') {
        router.push('/tables');
    } else {
        router.push('/tables'); 
    }
};

// Ao montar, verifica se o caixa está aberto
onMounted(() => {
    cashStore.checkStatus();
});
</script>

<style scoped>
.animate-fade-in-up { animation: fadeInUp 0.8s ease-out; }
@keyframes fadeInUp { 
    from { opacity: 0; transform: translateY(30px); } 
    to { opacity: 1; transform: translateY(0); } 
}
</style>